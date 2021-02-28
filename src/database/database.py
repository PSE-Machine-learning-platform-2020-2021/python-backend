# coding=utf-8
"""
This file contains the data base connection handling class DataBase
"""
import json
import pickle

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
        result: set[int] = []
        for row in cursor.fetchall():
            result |= row["type"]
        return sorted(result)

    def get_stuff(self, classifier_id: int) -> tuple:
        """
        This method retrieves a classifier and the scaler associated with it from their respecting database tables.
        If the underlying database connector mechanics raise Errors or Exceptions while doing this, they are not
        handled here. If there is more than one result with the specified ID, a ValueError is raised, as the database
        is corrupted in this case.
        :param classifier_id: The id of the classifier in its database table.
        :return: A classifier object and a scaler object bundled together in a tuple.
        """
        query_cls = """SELECT * FROM classifiers WHERE Id = %s"""
        query_scl = """SELECT * FROM scalers WHERE Id = %s"""
        data_tuple_cls = classifier_id,
        cursor = self.data_base.cursor(dictionary=True)
        cursor.execute(query_cls, data_tuple_cls)
        result = cursor.fetchall()
        if cursor.rowcount > 1:
            raise ValueError
        classifier = pickle.loads(result[0]["Classifier"])
        if "Scaler" in result[0]:
            data_tuple_scl = result[0]["Scaler"],
        else:
            data_tuple_scl = classifier_id,
        cursor.execute(query_scl, data_tuple_scl)
        result = cursor.fetchall()
        if cursor.rowcount > 1:
            raise ValueError
        scaler = pickle.loads(result[0]["Scaler"])
        return classifier, scaler

    def put_stuff(self, scaler, data_set_ids, features, sensors, classifier):
        """
        This method puts a scaler, a set of data set ids, a set of features and a classifier into a database.
        :param features: A list of used features for the scaler
        :param data_set_ids: A list of data set ids used for the scaler
        :param scaler: The scaler corresponding to the classifier
        :param sensors: The sensors used for collecting the datasets of the model.
        :param classifier: A classifier from sklearn.
        :return: The Id of the classifier ("AI model ID").
        """

        def put_classifier(cl: object, sens: list) -> int:
            """
            This method puts a classifier into the classifier database table.
            If the underlying database connector mechanics raise Errors or Exceptions
            while doing this, they are not handled here.
            :param cl: The classifier itself.
            :param sens: The sensors used in the datasets for the model.
            :return: the id of the classifier in its database table.
            """
            cursor = self.data_base.cursor()
            cl_input = pickle.dumps(cl)
            q = """INSERT INTO classifiers (Classifier, Sensors) VALUES (%s, %s)"""
            dt = cl_input, sens
            cursor.execute(q, dt)
            self.data_base.commit()
            return cursor.lastrowid

        def put_scaler(sc: object, scaler_type: str, ft: list, data_sets: list) -> int:
            """
            This method puts a new scaler into the scaler table in the database.
            If the underlying database connector mechanics raise Errors or Exceptions
            while doing this, they are not handled here.
            :param ft: A list of all features that where extracted from the data used for this scaler.
            :param sc: The scaler object itself
            :param scaler_type: The type of the scaler object for later retrieving the scaler.
            :param data_sets: The datasets the scaler was fitted to, also for later retrieving the scaler.
            :return: The id of the scaler in its database table.
            """
            cursor = self.data_base.cursor()
            data_sets.sort()
            features.sort()
            sc_input = pickle.dumps(sc)
            q = """INSERT INTO scalers (Scaler, ScalerType, Features, DataSets) VALUES (%s, %s, %s, %s)"""
            dt = sc_input, scaler_type, ft, data_sets
            cursor.execute(q, dt)
            self.data_base.commit()
            return cursor.lastrowid

        scaler_id = put_scaler(scaler, scaler.__class__.__name__, features, data_set_ids)
        classifier_id = put_classifier(classifier, sensors)

        if classifier_id != scaler_id:
            query = """UPDATE classifiers SET Scaler = %s WHERE Id = %s"""
            data_tuple = scaler_id, classifier_id
            csr = self.data_base.cursor()
            csr.execute(query, data_tuple)
            self.data_base.commit()

        return classifier_id
