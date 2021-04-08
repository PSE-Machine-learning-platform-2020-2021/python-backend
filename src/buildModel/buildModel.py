# coding=utf-8
"""This file describes sort of a function for the TypeScript front end. It executes all necessary steps to build and
train an AI model. """
import sys
from pathlib import Path

from sklearn.exceptions import ConvergenceWarning

sys.path.append(str(Path(__file__).parent.parent))

import json
import os

from typing import Union

import numpy as np
import pandas
import tsfresh
from pandas import DataFrame, Series
from sklearn.dummy import DummyClassifier
from sklearn.ensemble import RandomForestClassifier
from sklearn.impute import SimpleImputer
from sklearn.neighbors import KNeighborsClassifier
from sklearn.neural_network import MLPClassifier
from sklearn.preprocessing import Normalizer, MinMaxScaler, QuantileTransformer, RobustScaler, StandardScaler
from sklearn.svm import SVC
from tsfresh.feature_extraction import ComprehensiveFCParameters

from database.database import Database


class IllegalArgumentError(OSError):
    """No commandline arguments passed error"""
    pass


def fetch_parameters() -> dict:
    """
    This method extracts the command line arguments and resolves them into usable data for this script.
    This means the name of the temporary json file where the post request parameters are stored in.
    The json file needs to contain the following data in exact the described format in order to be usable:

    {
        "imputator": "<ImputatorType>",

        "dataSets": [
            1,

            <...>,

            42
        ],

        "features": [
            "<Feature#1>",

            "<...>",

            "<Feature#n>"
        ],

        "scaler": "<Scaler>",

        "classifier": "<Classifier>",

        "projectID": 1,

        "trainingDataPercentage": 0.8,

        "slidingWindowSize": 128,

        "slidingWindowStep": 64
    }

    The numbers in 'datasets' are just the ids of the datasets to use in the model.

    The strings in 'features', 'scaler' and 'classifier' are just the constants of the corresponding enums as strings.

    The last three values are optional.

    'trainingDataPercentage' must be in range (0;1).

    'slidingWindowSize' and 'slidingWindowStep' must be larger than 0 and integer numbers.

    The file must of course be correct json format.

    If there is more information in the json file, this is no problem.

    There is no overall covering of the given specifications!

    The order of appearance is not necessary.

    :return: All the contents of the specified json file as dict.
    """
    if len(sys.argv) < 2:
        raise IllegalArgumentError()
    file_path: str = " ".join(sys.argv[1:])
    with open(file_path) as file:
        data: dict = json.load(file)
        file.close()
    os.remove(file_path)
    if "dataSets" not in data or "projectID" not in data:
        raise IndexError()
    if "features" not in data:
        data["features"] = []
    if "scaler" not in data:
        data["scaler"] = ""
    if "classifier" not in data:
        data["classifier"] = []
    return data


def choose_features(features_from_outside: list[str]) -> list[str]:
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
        'KURTOSIS': "kurtosis"
    }
    if len(features_from_outside) == 0:
        return [features_available[x] for x in features_available]
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
    return DummyClassifier()


def choose_imputator(name: str) -> tuple:
    """
    Yes, I know, it should be named 'imputer' but well ... I don't care - 'Imputator' sounds like 'Terminator' and
    because of this it is fine. Ok. To the method:

    This method acts as a switch over the Imputation enum type from the TypeScript front end and creates out of a
    constant of this enum an imputator object from sklearn and returns it.

    :param name: The name of the imputator type as of Imputation enum in TypeScript
    :return: Two imputator objects from sklearn.impute corresponding to the passed name. There are two as we need two.
             Believe me.
    """
    options = {
        "MEAN": SimpleImputer()
    }
    if name in options:
        return options[name], options[name]
    return SimpleImputer(), SimpleImputer()


def impute(data: DataFrame, imputator: Union[SimpleImputer]) -> DataFrame:
    """
    Executes Imputation over a data frame

    :param data: The data frame object on which imputation is to be performed
    :param imputator: an object doing this as one does it with sklearn.impute imputers.
    :return: the same data frame just with imputed values.
    """
    data = data.replace([np.inf, -np.inf], np.NaN)
    if data.isna().values.any():
        for x in data.columns:
            if data[x].isna().values.all():
                data[x] = 0.0
        imputator.fit(data)
        return DataFrame(imputator.transform(data), columns=data.columns, index=data.index)
    return data


def create_time_slices(data: list[DataFrame], imputer: Union[SimpleImputer], slidingWindowSize=128,
                       slidingWindowStep=64) -> tuple[list[DataFrame], list[int]]:
    """
    This method cuts timeline based data sets into slices specified by passed parameters.

    :param slidingWindowStep: How many data points should lie between the beginning of two consecutive chunks?
                    Must lie between 1 and chunk_size inclusively
    :param slidingWindowSize: How many data points a chunk should contain?
                    Must be larger than zero.
    :param data:    A list of pandas DataFrame objects containing timeline based data to be transformed.
                    Those Dataframe objects must contain a column 'label' as last column.
    :param imputer: An object from sklearn.impute or anything performing similar, used to impute numbers on bad values.
    :returns: Training data and after that the corresponding labels.
    """
    if slidingWindowSize < 1:
        raise ValueError('Sliding Window Size smaller than 1.')
    if slidingWindowStep < 1:
        raise ValueError('Step Size smaller than 1.')
    if slidingWindowStep > slidingWindowSize:
        raise ValueError('Step Size bigger than Sliding Window Size.')
    if any([d.columns[-1] != "label" for d in data]):
        raise ValueError('At least one data set does not contain a column labelled "label" as last column.')
    x: list[DataFrame] = []
    y: list[int] = []
    for df in data:
        labels = np.array(df["label"].fillna(-1))
        df = impute(df, imputer)
        df["label"] = labels
        local_cs = slidingWindowSize
        local_step = slidingWindowStep
        if local_cs > df.shape[0] // 4:
            local_cs = df.shape[0] // 4
        if local_step > local_cs:
            local_step = local_cs
        for i in range(0, df.shape[0] - local_cs + 1, local_step):
            data_x: DataFrame = df.iloc[i:i + local_cs, :-1]
            data_y: int = df.iloc[i:i + local_cs, -1].value_counts().index[0]
            x.append(data_x)
            y.append(data_y)
    return x, y


def extract_features(ft_list: list[str], data: list[DataFrame], label: list[int], imputer: Union[SimpleImputer])\
        -> DataFrame:
    """
    This method performs the slidingWindowStep of feature extraction on a list of DataFrames from pandas.

    :param ft_list: a list of features generated by method 'choose_features'
    :param data:    The data sets on which the feature extraction is to be performed.
    :param label:   The labels column belonging to the data passed. It is expected to have the same length as data
    :param imputer: An object from sklearn.impute or anything that does the job the same way for imputing numbers that
                    have been corrupted in feature extraction.
    :return:        A list containing all the data with extracted features on them.
    """
    if len(data) != len(label) and len(label) > 0:
        raise ValueError("Input data does not contain same amount of entries as labels.")
    settings = {key: ComprehensiveFCParameters()[key] for key in ft_list}
    results: list[DataFrame] = []
    for i, x in enumerate(data):
        block = x.copy()
        block["id"] = i + 1
        results.append(tsfresh.extract_features(block, column_id="id", default_fc_parameters=settings,
                                                disable_progressbar=True))
    output: DataFrame = impute(pandas.concat(results), imputer)
    if len(label) > 0:
        output["label"] = label
    return output


def partition_data(data: DataFrame, percentage=0.8) -> tuple[DataFrame, Series, DataFrame, Series]:
    """
    This method breaks up the passed data into a part of paired X/Y-axis training data and a part of X/Y-axis labeled
    testing data.

    :param data: The data to split up
    :param percentage: The percentage of the input data to be used as training data. Has to be in interval (0;1].
    :returns: Four chunks of data: X-axis training, Y-axis training, X-axis testing, Y-axis testing
    """

    if percentage is None or not (0 < percentage <= 1):
        raise ValueError("Param percentage must lie in the open interval (0;1]. Passed value was " + str(percentage))
    if data is None:
        raise ValueError("No data passed!")
    cut = int((data.shape[0] - 1) * percentage)
    train_x = data.iloc[:cut, :-1]
    train_y = data.iloc[:cut, -1]
    test_x = data.iloc[cut:, :-1]
    test_y = data.iloc[cut:, -1]
    return train_x, train_y, test_x, test_y


def preprocess_data(data: DataFrame,
                    scaler: Union[StandardScaler, MinMaxScaler, Normalizer, QuantileTransformer, RobustScaler],
                    ready_to_use=False) -> DataFrame:
    """
    This method performs the data preprocessing slidingWindowStep.

    :param ready_to_use: hints that the scaler passed is already fitted to our data so we don't have to do this again.
    :param data: The data to process
    :param scaler: The scaler to use for this task
    :return: Processed data.
    """
    if not ready_to_use:
        scaler.fit(data)
    return DataFrame(scaler.transform(data), columns=data.columns, index=data.index)


def train_classifier(x_axis_data: DataFrame, y_axis_data: Series,
                     classifier: Union[MLPClassifier, RandomForestClassifier, KNeighborsClassifier, SVC]) -> None:
    """
    This method performs training on the classifier.

    :param x_axis_data: Transformed X training data
    :param y_axis_data: Untransformed Y training data
    :param classifier: The classifier to be trained.
    :return: Yet not fixed
    """
    classifier.fit(x_axis_data, y_axis_data)


def notify_server(model_id: int):
    """
    This method is used to notify the server that the calculation of an ai model is done.
    The server is now asked to inform the user in a appropriate way.

    :param model_id: The id of the classifier of the finished model in the data base.
    :return: Nothing.
    """
    print(str(model_id))


if __name__ == "__main__":
    # first of all - get our execution parameters!
    exec_params = fetch_parameters()
    # Second, pick all available objects already available here.
    features = choose_features(exec_params["features"])
    scaler = choose_scaler(exec_params["scaler"])
    classifier = choose_classifier(exec_params["classifier"])
    imputators = choose_imputator(exec_params["imputator"])
    # Get Access to our data base
    database = Database(exec_params["dataSets"], exec_params["projectID"])
    # Get the data sets we need from the database
    datasets = database.get_data_sets()
    # Prepare the data
    x_data, y_data = create_time_slices(datasets, imputators[0], **{x: exec_params[x]
                                                                    for x in ("slidingWindowSize", "slidingWindowStep")
                                                                    if x in exec_params})
    # Extract all the features desired.
    featured_data = extract_features(features, x_data, y_data, imputators[1])
    # After that, part our data into one part of training and one part of testing data.
    if "trainingDataPercentage" in exec_params:
        x_training, y_training, x_testing, y_testing = partition_data(featured_data,
                                                                      exec_params["trainingDataPercentage"])
    else:
        x_training, y_training, x_testing, y_testing = partition_data(featured_data)
    # Now preprocess our data through our scaler
    x_training_processed = preprocess_data(x_training, scaler)
    x_testing_processed = preprocess_data(x_testing, scaler, True)
    # as second to last slidingWindowStep, train our classifier!
    try:
        train_classifier(x_training_processed, y_training, classifier)
    except ConvergenceWarning:
        notify_server(-1)
        exit(0)
    # as last, put everything in the data base and be done.
    model_id = database.put_stuff(classifier, scaler, features=features)
    # as very last, say our server hello, so that it sends an email.
    notify_server(model_id)
