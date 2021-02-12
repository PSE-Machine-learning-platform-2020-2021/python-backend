# coding=utf-8
"""
This file contains the data base connection handling class DataBase
"""
import pickle

import mysql.connector
import pandas as pd

from configReader import ConfigReader


class Database:
    """
    This class bundles together all needed database accessing needed for current plan of python part.
    TODO Get the data base running!
    """

    def __init__(self):
        """
        Creates an object of Database class based on configuration file.
        """
        config = ConfigReader()
        db_data = config.get_values("DB")
        self.data_base = mysql.connector.connect(*db_data)

    def get_data_sets(self, indices: list) -> tuple:
        """
        This method retrieves all datasets specified by parameter indices from the database specified in config file.
        :param indices: A list containing the database indices of all of the desired data sets for further processing.
        :return: Retrieved datasets or exceptions/errors based on the reason that caused the request to fail.
        """
        cursor = self.data_base.cursor(dictionary=True)
        query = """SELECT * FROM dataSets WHERE Id = %s"""
        output = []
        for x in indices:
            data_tuple = x,
            cursor.execute(query, data_tuple)
            result = cursor.fetchone()
            result.update("DataSet", pd.DataFrame(result["DataSet"]))
            output.append(result)
        return tuple(output)

    def get_scalers(self, scaler_type: str, features: list, data_set_ids: list) -> tuple:
        """
        This method gets all scalers out of their database table that have the corresponding
        type and are fitted to the given datasets and features.
        If the underlying database connector mechanics raise Errors or Exceptions
        while doing this, they are not handled here.
        :param features: All the features extracted on the data used within the scaler.
        :param scaler_type: The class name of the scaler type as of <object>.__class__.__name__
        :param data_set_ids: The ids of the datasets used.
        :return: A tuple of zero or more Scalers from sklearn.
        """
        cursor = self.data_base.cursor()
        cursor.execute("SELECT Scaler, Features, DataSets FROM scalers WHERE ScalerType = {st}".format(st=scaler_type))
        result = cursor.fetchall()
        features.sort()
        data_set_ids.sort()
        output = []
        for x in result:
            if x[1].sort() == features and x[2].sort() == data_set_ids:
                output.append(pickle.loads(x[0]))
        return tuple(output)

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
