# coding=utf-8
"""This file describes sort of a function for the TypeScript front end. It executes all necessary steps to build and
train an AI model. """
import json
import os
import sys

import pandas as pd
from sklearn.dummy import DummyClassifier
from sklearn.ensemble import RandomForestClassifier
from sklearn.neighbors import KNeighborsClassifier
from sklearn.neural_network import MLPClassifier
from sklearn.preprocessing import Normalizer, MinMaxScaler, QuantileTransformer, RobustScaler, StandardScaler
from sklearn.svm import SVC

from database import Database


class IllegalArgumentError(OSError):
    """No commandline arguments passed error"""
    pass


def fetch_parameters():
    """
    This method extracts the command line arguments and resolves them into usable data for this script.
    This means the name of the temporary json file where the post request parameters are stored in.
    The json file needs to contain the following data in exact the described format in order to be usable:
    {
        'dataSets': [
            1,
            <...>,
            42
        ],
        'features': [
            "<Feature#1>",
            "<...>",
            "<Feature#n>"
        ],
        'scaler': "<Scaler>",
        'classifier': "<Classifier>",
        'trainingDataPercentage': 0.8,
        'slidingWindowSize': 128,
        'slidingWindowStep': 64
    }
    The numbers in 'datasets' are just the ids of the datasets to use in the model.
    The strings in 'features', 'scaler' and 'classifier' are just the constants of the corresponding enums as strings.
    The last three values are optional.
    'trainingDataPercentage' must be in range (0;1).
    'slidingWindowSize' and 'slidingWindowStep' must be larger than 0 and integer numbers.
    The file must of course be correct json format.
    If there is more information in the json file, this is no problem.
    :return: All the contents of the specified json file as dict.
    """
    if len(sys.argv) < 2:
        raise IllegalArgumentError()
    file_path: str = " ".join(sys.argv[1:])
    with open(file_path) as file:
        data: dict = json.load(file)
        file.close()
    os.remove(file_path)
    if "dataSets" not in data:
        raise IndexError()
    if "features" not in data:
        data["features"] = []
    if "scaler" not in data:
        data["scaler"] = ""
    if "classifier" not in data:
        data["classifier"] = []
    return data


def choose_features(features_from_outside):
    """
    This method transforms a list of feature names out of the Extraction enum from TypeScript into a list of
    ready-to-use features.
    :param features_from_outside: The feature list from Extraction enum
    :return: A list consisting of their corresponding name here.
    """
    features_available = {
        'MIN': "minimum",
        'MAX': "maximum",
        'VARIANCE': "variance",
        'ENERGY': "abs_energy",
        'MEAN': "mean",
        'AUTOREGRESSIVE': "ar_coefficient",
        'IQR': "quantile",
        'SKEWNESS': "skewness",
        'KURTOSIS': "kurtosis",
        'FOURIER_TRANSFORM': "???"
    }
    if len(features_from_outside) == 0:
        return features_available
    return [features_available[x] for x in features_from_outside if x in features_available]


def choose_scaler(name: str):
    """
    This method acts as a switch over the scaler type and creates out of a given type name a scaler object.
    :param name: The name of the scaler type as of Preprocessing enum in TypeScript
    :return: A Scaler object matching the type of the passed type name
    """
    options = {
        'MIN_MAX': MinMaxScaler(),
        'NORMALIZER': Normalizer(),
        'QUANTILE_TRANSFORMER': QuantileTransformer(),
        'ROBUST_SCALER': RobustScaler(),
        'STANDARD_SCALER': StandardScaler()
    }
    if name in options:
        return options[name]
    else:
        return StandardScaler()


def choose_classifier(name: str):
    """
    This method acts as a switch over the classifier type and creates out of a given type name a classifier object.
    :param name: The name of the classifier type as of Classifier enum in TypeScript
    :return: A Classifier object matching the type of the passed type name (more or less). MLP creates no ponies.
    """
    options = {
        'MLP': MLPClassifier(),
        'RANDOM_FOREST': RandomForestClassifier(),
        'K_NEIGHBORS': KNeighborsClassifier(),
        'SVM': SVC()
    }
    if name in options:
        return options[name]
    else:
        return DummyClassifier()


def create_time_slices(data, chunk_size=128, step=64):
    """
    This method cuts timeline based data sets into slices specified by passed parameters.
    :param step: How many data points should lie between the beginning of two consecutive chunks?
    :param chunk_size: How many data points a chunk should contain
    :param data: A pandas DataFrame object containing timeline based data to be transformed.
    :returns: Training data and after that the corresponding labels.
    """
    x = []
    y = []
    for data_set in data:
        df = pd.DataFrame(data_set)
        for i in range(0, df.shape[0] - chunk_size + 1, step):
            data_x = df.iloc[i:i + chunk_size, :-1]
            data_y = df.iloc[i:i + chunk_size, -1].value_counts().index[0]
            x.append(data_x)
            y.append(data_y)
    return x, y


def extract_features(features: list, data: list):
    """
    This method performs the step of feature extraction on a list of DataFrames from pandas.
    :param features: a list of features generated by method 'choose_features'
    :param data: The data sets on which the feature extraction is to be performed.
    :return: TODO
    """
    raise NotImplementedError()


def partition_data(x_data: list, y_data: list, percentage=0.8):
    """
    This method breaks up the passed data into a part of paired X/Y-axis training data and a part of X/Y-axis labeled
    testing data.
    :param x_data: The data to split up
    :param y_data: The data labels corresponding to x_data
    :param percentage: The percentage of the input data to be used as training data.
    :returns: Four chunks of data: X-axis training, Y-axis training, X-axis testing, Y-axis testing
    """
    train_x = x_data[:int(len(x_data) * percentage)]
    train_y = y_data[:int(len(y_data) * percentage)]
    test_x = x_data[int(len(x_data) * percentage):]
    test_y = y_data[int(len(y_data) * percentage):]
    return train_x, train_y, test_x, test_y


def preprocess_data(data: pd.DataFrame, scaler, read_to_use=False):
    """
    This method performs the data preprocessing step.
    :param read_to_use: hints that the scaler passed is already fitted to our data so we don't have to do this again.
    :param data: The data to process
    :param scaler: The scaler to use for this task
    :return: Processed data.
    """
    #     :param training_data: Indicates that this data is training data for an AI model, if set to True.
    if not read_to_use:
        scaler.fit(data)
    return scaler.transform(data)


def train_classifier(x_axis_data, y_axis_data, classifier):
    """
    This method performs training on the classifier.
    :param x_axis_data: Transformed X training data
    :param y_axis_data: Untransformed Y training data
    :param classifier: The classifier to be trained.
    :return: Yet not fixed
    """
    classifier.fit(x_axis_data, y_axis_data)


def notify_server(model_id):
    pass


if __name__ == "__main__":
    # first of all - get our execution parameters!
    exec_params = fetch_parameters()
    # Second, pick all available objects already available here.
    features = choose_features(exec_params["features"])
    scaler = choose_scaler(exec_params["scaler"])
    classifier = choose_classifier(exec_params["classifier"])
    # Get Access to our data base
    database = Database()
    # Get the data sets we need from the database
    datasets = database.get_data_sets(exec_params["dataSets"])
    # Prepare the data
    x_data, y_data = create_time_slices(datasets, *(exec_params[x] for x in ("slidingWindowSize", "slidingWindowStep")
                                                    if x in exec_params))
    # Extract all the features desired.
    featured_data = extract_features(features, x_data)
    # After that, part our data into one part of training and one part of testing data.
    if "trainingDataPercentage" in exec_params:
        x_training, y_training, x_testing, y_testing = partition_data(featured_data, y_data,
                                                                      exec_params["trainingDataPercentage"])
    else:
        x_training, y_training, x_testing, y_testing = partition_data(featured_data, y_data)
    # Look up, if we can find a scaler matching our parameters.
    ready_scalers = database.get_scalers(str(scaler), features, exec_params["dataSets"])
    # Fit our scaler to our data sets - or leave that out because we already have one in our data base.
    ready = len(ready_scalers) > 0
    if ready:
        scaler = ready_scalers[0]
    # Now preprocess our data through our scaler
    x_training_processed = preprocess_data(x_training, scaler, ready)
    x_testing_processed = preprocess_data(x_testing, scaler, ready)
    # as second to last step, train our classifier!
    train_classifier(x_training_processed, y_training, classifier)
    # as last, put everything in the data base and be done.
    model_id = database.put_stuff(scaler, features, exec_params["dataSets"], classifier)
    # as very last, say our server hello, so that it sends an email.
    notify_server(model_id)