# coding=utf-8
"""
This file contains the data base connection handling class DataBase
"""
import sys
from pathlib import Path
sys.path.append(str(Path(__file__).parent.parent))

import json
import pickle
from typing import Any

import mysql.connector
import pandas as pd
import numpy as np
from pandas import DataFrame

from config.configReader import ConfigReader


class Database:
    """
    This class bundles together all needed database accessing needed for current plan of python part.
    """

    def __init__(self, data_set_ids: list[int], project_id: int):
        """
        Creates an object of Database class based on configuration file.
        :param data_set_ids: A list containing the database indices of all of the desired data sets for further
                             processing.
        :param project_id: The running number of the project this process belongs to.
        """
        self.project_id = project_id
        self._labels: dict[int, str] = {}
        self._labels_reversed: dict[str, int] = {}
        self.data_set_ids = data_set_ids
        config = ConfigReader()
        db_data = config.get_values("DB")
        self.data_base = mysql.connector.connect(**db_data)
        self.data_sets: list[DataFrame] = []
        self.sensor_type_ids: list[int] = []

    def get_data_sets(self) -> list[DataFrame]:
        """
        This method retrieves all datasets specified by parameter indices from the database specified in config file.

        It loads those data sets only once from the data base to save time, so you have to empty the data_sets field if
        you wish to refresh the result.
        
        Currently uses SensorID as SensorTypeID as sensor name for the purpose of labelling the columns.

        :return: A tuple containing all data rows found and matching one of the passed data set ids, grouped together
                 by their data set id into pandas Dataframe objects.
        """
        if len(self.data_sets) > 0:
            return self.data_sets

        cursor = self.data_base.cursor(dictionary=True)
        # With this query we select all data rows belonging to the given data sets together with their name and
        # the name of the sensor that was used for them.
        query = """SELECT dataJSON, 
                          name, 
                          sensorID AS sensorName
                   FROM Datarow
                   WHERE datasetID = %s"""
        for i in self.data_set_ids:
            # Execute query for every single dataset
            cursor.execute(query, (i, ))
            data_set: dict[str, dict] = {}
            times = set()

            # Integrate all the data rows found into the dataset
            for data_row in cursor.fetchall():
                name: str = str(data_row["sensorName"]) if data_row["name"] is None else str(data_row["name"])
                if name in data_set:
                    i = 0
                    while name + "R" + str(i) in data_set:
                        i += 1
                    name += "R" + str(i)
                data_rows_loaded = json.loads(data_row["dataJSON"])
                for index, value in enumerate(data_rows_loaded[0]["value"]):
                    dr_name = name + " " + str(index)
                    data_set[dr_name] = {x["relativeTime"]: x["value"][index] for x in data_rows_loaded}
                    times |= set(data_set[dr_name].keys())

            # Ensure that all data rows feature exactly equal sets of timestamps and in all cases have values there and
            # that all values are in correct ascending order by timestamp
            for key in data_set.keys():
                data_set[key] = {x: np.NaN for x in sorted(times)} | data_set[key]

            if len(data_set) == 0:
                self.data_set_ids.remove(i)
            else:
                ds = pd.DataFrame(data_set)
                ds.id = i
                self.data_sets.append(ds)
        if self.project_id > 0:
            for x in self.data_sets:
                x["label"] = -1
                self._get_labels()
        return self.data_sets

    def get_sensor_type_ids(self) -> list[int]:
        """
        This method retrieves information from the Sensor database table.
        This information contains the types of the sensors used in the
        data sets whose ids are passed as this function's parameter.

        Please note that you have to reset the field sensor_type_ids in order to
        refresh the result if you call that method twice.

        :return: A list of numbers representing sensor types used in the data sets passed by id
        """
        if len(self.sensor_type_ids) > 0:
            return self.sensor_type_ids
        cursor = self.data_base.cursor(dictionary=True)
        query = """SELECT sensorID AS type
                   FROM Datarow 
                   WHERE datasetID IN """
        query += self._tuple_ize_dsi()
        cursor.execute(query)
        result: set[int] = set()
        for row in cursor.fetchall():
            result.add(row["type"])
        self.sensor_type_ids = sorted(result)
        return self.sensor_type_ids

    def get_stuff(self, classifier_id: int) -> tuple[Any, Any, list[int], dict[int, str], list[str]]:
        """
        This method retrieves a classifier and the scaler associated with it from their respecting database tables.
        If the underlying database connector mechanics raise Errors or Exceptions while doing this, they are not
        handled here. If there is more than one result with the specified ID, a ValueError is raised, as the database
        is corrupted in this case.

        :param classifier_id: The id of the classifier in its database table.
        :return: A classifier object and a scaler object bundled together in a tuple.
        """
        query = """SELECT * FROM Classifiers WHERE ID = %s"""
        data_tuple = classifier_id,
        cursor = self.data_base.cursor(dictionary=True)
        cursor.execute(query, data_tuple)
        result = cursor.fetchall()
        if cursor.rowcount > 1:
            raise ValueError
        classifier = pickle.loads(result[0]["Classifier"])
        scaler = pickle.loads(result[0]["Scaler"])
        self.sensor_type_ids, self._labels = json.loads(result[0]["Sensors"]), json.loads(result[0]["LabelsTable"])
        self._labels_reversed = {v: k for k, v in self._labels.items()}
        features = json.loads(result[0]["Features"])
        return classifier, scaler, self.sensor_type_ids, self._labels, features

    def put_stuff(self, classifier, scaler, sensors: list[int] = None, *, features: list[str]) -> int:
        """
        This method puts a classifier, a scaler, the label-to-number association table and the list of sensor types
        that were used when collecting the data for the data sets used in the training of the ai model into the
        corresponding data base table.

        :param features: The features that were extracted from the model training data.
        :param classifier: A classifier from sklearn. It is then pickled and stored.
        :param scaler: The scaler that was used to transform the training data for the classifier passed right before.
        :param sensors: The ids of the types of the sensors used for collecting the data that was used to train the
                        classifier passed as first parameter and the scaler passed as second one.
                        If not passed, the list stored in this object is used, as long as it is not empty. In the latter
                        case, the list is internally collected.
        :return: The Id of the classifier ("AI model ID").
        """
        query = """INSERT INTO Classifiers (Classifier, Scaler, Sensors, LabelsTable, ProjectID, Features) 
        VALUES (%s, %s, %s, %s, %s, %s)"""
        cursor = self.data_base.cursor()
        self._get_labels()
        if sensors is None:
            sensors = self.get_sensor_type_ids()
        cursor.execute(query,
                       (pickle.dumps(classifier),
                        pickle.dumps(scaler),
                        json.dumps(sensors),
                        json.dumps(self._labels),
                        self.project_id,
                        json.dumps(features)))
        self.data_base.commit()
        return cursor.lastrowid

    def _get_labels(self) -> None:
        """
        This PRIVATE method is not meant to be called from outside the class.
        It gathers information about labels on the data requested via this specific instance of this class.
        It also applies them onto the data sets.

        If there are no labels provided for the data in question, a value error is raised.
        """
        if len(self._labels) > 0:
            return
        query = """SELECT datasetID, name, start, end FROM Label WHERE datasetID IN """
        query += self._tuple_ize_dsi()
        cursor = self.data_base.cursor(dictionary=True)
        cursor.execute(query)
        label_names: set[str] = set()
        result = cursor.fetchall()
        if len(result) == 0:
            raise ValueError
        for row in result:
            row["name"] = row["name"].strip().casefold().upper()  # This ensures unified all-uppercase format
            label_names.add(row["name"])
        self._labels = {k: v for k, v in enumerate(sorted(label_names))}
        self._labels_reversed = {v: k for k, v in enumerate(sorted(label_names))}
        for ds in self.data_sets:
            if "label" not in ds.columns:
                ds["label"] = -1
            timestamps = list(ds.index)
            for row in result:
                if row["datasetID"] == ds.id:
                    new_label = []
                    for k, v in zip(timestamps, ds["label"]):
                        new_label.append(self._labels_reversed[row["name"]] if row["start"] <= k <= row["end"] else v)
                    ds["label"] = new_label
                else:
                    continue

    def _tuple_ize_dsi(self) -> str:
        return str(tuple(self.data_set_ids)) if len(self.data_set_ids) > 1 else "(" + str(self.data_set_ids[0]) + ")"