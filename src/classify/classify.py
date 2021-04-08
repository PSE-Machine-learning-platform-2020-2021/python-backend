# coding=utf-8
"""
This file is sort of a function for the TypeScript front end and handles requests to classify data.
"""
import sys
from pathlib import Path
sys.path.append(str(Path(__file__).parent.parent))

import json
import os

from buildModel.buildModel import IllegalArgumentError
from database.database import Database


def fetch_parameters():
    """
    This method extracts the command line arguments and resolves them into usable data for this script.
    This means the name of the temporary json file where the post request parameters are stored in.
    The json file needs to contain the following data in exact the described format in order to be usable:

    {
        'dataSet': 1,

        'classifier': <Classifier_id>
    }

    The numbers in 'datasets' are just the ids of the datasets to use in the model.

    The string at 'classifier' is the id of the classifier in its data base.

    The file must of course be correct json format.

    If there is more information in the json file, this is no problem.

    There is no checking, if the given values are valid!

    :return: All the contents of the specified json file as dict.
    """
    if len(sys.argv) < 2:
        raise IllegalArgumentError()
    file_path: str = " ".join(sys.argv[1:])
    with open(file_path) as file:
        data: dict = json.load(file)
        file.close()
    os.remove(file_path)
    if "dataSet" not in data:
        raise IndexError()
    if "classifier" not in data:
        raise IndexError()
    return data


if __name__ == "__main__":
    # first of all - get our execution parameters!
    exec_params = fetch_parameters()
    database = Database([exec_params["dataSet"]])
    data_sets = database.get_data_sets()
    classifier, scaler, sensors, labels = database.get_stuff(exec_params["classifier"])
    scaled_data = scaler.transform(data_sets)
    prediction = classifier.predict(scaled_data)
    for x in prediction:
        print(labels[x])
