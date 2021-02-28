# coding=utf-8
"""
This file contains the data base connection handling class DataBase
"""
import json
import pickle
from typing import Any

import mysql.connector
import pandas as pd
import numpy as np
from pandas import DataFrame

from src.config.configReader import ConfigReader


class Database:
    """
    This class bundles together all needed database accessing needed for current plan of python part.
    """

    def __init__(self):
        """
        Creates an object of Database class based on configuration file.
        """
        config = ConfigReader()
        db_data = config.get_values("DB")
        self.data_base = mysql.connector.connect(*db_data)

    def get_data_sets(self, data_sets: list[int]) -> list[pd.DataFrame]:
        """
        This method retrieves all datasets specified by parameter indices from the database specified in config file.
        ATTENTION: This method does atm not load and append any labels to the data! But a label column is appended.
        TODO: Change that.
        :param data_sets: A list containing the database indices of all of the desired data sets for further processing.
        :return: A tuple containing all data rows found and matching one of the passed data set ids, grouped together
                 by their data set id into pandas Dataframe objects.
        """
        cursor = self.data_base.cursor(dictionary=True)
        # With this query we select all data rows belonging to the given data sets together with their name and
        # the name of the sensor that was used for them.
        query = """SELECT dataJSON, 
                          name, 
                          (SELECT sensorName FROM Sensor WHERE Sensor.sensorID = Datarow.sensorID) AS sensorName
                   FROM Datarow
                   WHERE datasetID = %s"""
        result: list[DataFrame] = []
        for i in data_sets:
            # Execute query for every single dataset
            cursor.execute(query, i)
            data_set: dict[str, dict] = {}
            times = set()

            # Integrate all the data rows found into the dataset
            for data_row in cursor.fetchall():
                name: str = data_row["sensorName"] if data_row["name"] is None else data_row["name"]
                data_set[name] = {x["relativeTime"]: x["value"] for x in json.loads(data_row["dataJSON"])}
                times |= set(data_set[name].keys())

            # Ensure that all data rows feature exactly equal sets of timestamps and in all cases have values there and
            # that all values are in correct ascending order by timestamp
            for key in data_set.keys():
                data_set[key] = {x: np.NaN for x in sorted(times)} | data_set[key]
            result.append(pd.DataFrame(data_set))

        # Ensure that there is always a label column ...
        for x in result:
            if "label" not in x:
                x["label"] = [np.NaN for x in x.index]
        return result

    def get_sensors(self, data_sets: list[int]) -> list[int]:
        """
        This method retrieves information from the Sensor database table.
        This information contains the types of the sensors used in the
        data sets whose ids are passed as this function's parameter.
        :param data_sets: A list containing the numeric ids of one or more data sets.
        :return: A list of numbers representing sensor types used in the data sets passed by id
        """
        cursor = self.data_base.cursor(dictionary=True)
        query = """SELECT sensorTypeID AS type
                   FROM Sensor 
                   WHERE sensorID 
                   IN (SELECT sensorID FROM Datarow WHERE datasetID in %s)"""
        cursor.execute(query, tuple(data_sets))
        result: set[int] = set()
        for row in cursor.fetchall():
            result |= row["type"]
        return sorted(result)

    def get_stuff(self, classifier_id: int) -> tuple[Any, Any, list[int], dict[int, str]]:
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
        sensors, labels_table = json.loads(result[0]["Sensors"]), json.loads(result[0]["LabelsTable"])
        return classifier, scaler, sensors, labels_table

    def put_stuff(self, classifier, scaler, sensors, labels_table: dict[int, str] = None) -> int:
        """
        This method puts a classifier, a scaler, the label-to-number association table and the list of sensor types
        that were used when collecting the data for the data sets used in the training of the ai model into the
        corresponding data base table.
        :param classifier: A classifier from sklearn. It is then pickled and stored.
        :param scaler: The scaler that was used to transform the training data for the classifier passed right before.
        :param sensors: The sensors used for collecting the data of the data sets used as training data for the
                        classifier passed as first parameter.
        :param labels_table: A dict containing numbers as keys associating with label names. Those numbers which must be
                             non-negative are used to classify input - or training - data by the ai model.
        :return: The Id of the classifier ("AI model ID").
        """
        query = """INSERT INTO Classifiers (Classifier, Scaler, Sensors, LabelsTable) VALUES (%s, %s, %s, %s)"""
        cursor = self.data_base.cursor()
        cursor.execute(query,
                       (pickle.dumps(classifier),
                        pickle.dumps(scaler),
                        json.dumps(sensors),
                        json.dumps(labels_table)))
        self.data_base.commit()
        return cursor.lastrowid
