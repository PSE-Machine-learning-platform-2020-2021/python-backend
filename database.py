import mysql.connector

from configReader import ConfigReader


class Database:
    """
    This class bundles toghether all needed database accessing needed for current plan of python part.
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

    def get_scalers(self, scaler_type: str, data_sets: list) -> tuple:
        """
        This method gets all scalers out of their database table that have the corresponding
        type and are fitted to the given datasets.
        Raises an error, if mysql.connector module does so.
        :param scaler_type: The class name of the scaler type.
        :param data_sets: The ids of the datasets used.
        :return: A list of zero or more Scalers from sklearn.
        """
        raise NotImplementedError()

    def put_scaler(self, scaler: object, scaler_type: str, data_sets: list) -> int:
        """
        This method puts a new scaler into the scaler table in the database.
        If the underlying database connector mechanics raise Errors or Exceptions while doing this, they are re-raised here.
        :param scaler: The scaler object itself
        :param scaler_type: The type of the scaler object for later retrieving the scaler.
        :param data_sets: The datasets the scaler was fitted to, also for later retrieving the scaler.
        :return: The id of the scaler in its database table.
        """
        pass

    def put_classifier(self, classifier: object, scaler_id=0) -> int:
        """
        This method puts a classifier into the classifier database table.
        If the underlying database connector mechanics raise Errors or Exceptions while doing this, they are re-raised here.
        :param classifier: The classifier itself.
        :param scaler_id: An optional argument to inform this method there is already a scaler in scaler database table to
        be associated with this classifier. If no value larger than zero is passed, than this method will call put scaler
        and use the result for association.
        :return: the id of the classifier in its database table.
        """
        pass

    def get_stuff(self, classifier_id: int) -> tuple:
        """
        This method retrieves a classifier and the scaler associated with it from their respecting database tables.
        If the underlying database connector mechanics raise Errors or Exceptions while doing this, they are re-raised here.
        :param classifier_id: The id of the classifier in its database table.
        :return: A classifier object and a scaler object bundled together in a tuple.
        """
