# coding=utf-8
"""
This file contains the data base connection handling class DataBase
"""

import mysql.connector

from configReader import ConfigReader


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

    def get_data_sets(self, indices: list) -> tuple:
        """
        This method retrieves all datasets specified by parameter indices from the database specified in config file.
        :param indices: A list containing the database indices of all of the desired data sets for further processing.
        :return: Retrieved datasets or exceptions/errors based on the reason that caused the request to fail.
        """
        raise NotImplementedError()

    def get_scalers(self, scaler_type: str, features: list, data_set_ids: list) -> tuple:
        """
        This method gets all scalers out of their database table that have the corresponding
        type and are fitted to the given datasets and features.
        Raises an error, if mysql.connector module does so.
        :param features: All the features extracted on the data used within the scaler.
        :param scaler_type: The class name of the scaler type as of <object>.__class__.__name__
        :param data_set_ids: The ids of the datasets used.
        :return: A tuple of zero or more Scalers from sklearn.
        """
        raise NotImplementedError()

    def put_scaler(self, scaler: object, scaler_type: str, data_sets: list) -> int:
        """
        This method puts a new scaler into the scaler table in the database.
        If the underlying database connector mechanics raise Errors or Exceptions
        while doing this, they are re-raised here.
        :param scaler: The scaler object itself
        :param scaler_type: The type of the scaler object for later retrieving the scaler.
        :param data_sets: The datasets the scaler was fitted to, also for later retrieving the scaler.
        :return: The id of the scaler in its database table.
        """
        raise NotImplementedError()

    def put_classifier(self, classifier: object) -> int:
        """
        This method puts a classifier into the classifier database table.
        If the underlying database connector mechanics raise Errors or Exceptions
        while doing this, they are re-raised here.
        :param classifier: The classifier itself.
        :return: the id of the classifier in its database table.
        """
        raise NotImplementedError()

    def get_stuff(self, classifier_id: int) -> tuple:
        """
        This method retrieves a classifier and the scaler associated with it from their respecting database tables.
        If the underlying database connector mechanics raise Errors or Exceptions
        while doing this, they are re-raised here.
        :param classifier_id: The id of the classifier in its database table.
        :return: A classifier object and a scaler object bundled together in a tuple.
        """
        raise NotImplementedError()

    def put_stuff(self, scaler, data_set_ids, features, classifier):
        """
        This method puts a scaler, a set of data set ids, a set of features and a classifier into a database.
        :param features: A list of used features for the scaler
        :param data_set_ids: A list of data set ids used for the scaler
        :param scaler: The scaler corresponding to the classifier
        :param classifier: A classifier from sklearn.
        :return: The Id of the classifier ("AI model ID").
        """
        raise NotImplementedError()
