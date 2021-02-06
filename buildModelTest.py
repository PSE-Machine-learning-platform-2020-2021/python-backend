# coding=utf-8
"""
This file contains all unit tests for buildModel.py
"""
import io
import sys
import unittest
from contextlib import redirect_stdout
from unittest import TestCase

from sklearn.dummy import DummyClassifier
from sklearn.neural_network import MLPClassifier
from sklearn.preprocessing import StandardScaler, Normalizer

import buildModel


import sys


class BuildModelTest(TestCase):
    def test_fetch_parameters(self):
        sys.argv.append("testFile.json")
        with open("testFile.json", "w") as f:
            f.write("""
{
    "dataSets": [
        1,
        2,
        42
    ],
    "features": [
        "<Feature#1>",
        "<...>",
        "<Feature#n>"
    ],
    "scaler": "<Scaler>",
    "classifier": "<Classifier>",
    "trainingDataPercentage": 0.8,
    "slidingWindowSize": 128,
    "slidingWindowStep": 64
}
            """)
            f.close()
        self.assertEqual(buildModel.fetch_parameters(), dict(dataSets=[1, 2, 42],
                                                             features=["<Feature#1>", "<...>", "<Feature#n>"],
                                                             scaler="<Scaler>", classifier="<Classifier>",
                                                             trainingDataPercentage=0.8, slidingWindowSize=128,
                                                             slidingWindowStep=64))

    def test_choose_features(self):
        result = buildModel.choose_features(['MIN', 'ENERGY', 'FOURIER_TRANSFORM', 'Bullshit'])
        self.assertCountEqual(result, ["minimum", "abs_energy", "???"])

    def test_choose_scaler(self):
        result = buildModel.choose_scaler('MIN')
        self.assertIsInstance(result, StandardScaler().__class__)
        result = buildModel.choose_scaler('NORMALIZER')
        self.assertIsInstance(result, Normalizer().__class__)
        result = buildModel.choose_scaler("")
        self.assertIsInstance(result, StandardScaler().__class__)

    def test_choose_classifier(self):
        result = buildModel.choose_classifier('MIN')
        self.assertIsInstance(result, DummyClassifier().__class__)
        result = buildModel.choose_classifier('MLP')
        self.assertIsInstance(result, MLPClassifier().__class__)
        result = buildModel.choose_classifier("")
        self.assertIsInstance(result, DummyClassifier().__class__)

    @unittest.expectedFailure
    def test_create_time_slices(self):
        self.fail()

    @unittest.skip("TSFresh is not working as expected. Awaiting reparation.")
    def test_extract_features(self):
        self.fail()

    @unittest.expectedFailure
    def test_partition_data(self):
        self.fail()

    @unittest.expectedFailure
    def test_preprocess_data(self):
        self.fail()

    @unittest.expectedFailure
    def test_train_classifier(self):
        self.fail()

    def test_notify_server(self):
        f = io.StringIO()
        with redirect_stdout(f):
            buildModel.notify_server(713666)
            output = f.getvalue().strip()
        self.assertEqual("713666", output)

    def setUp(self) -> None:
        """
        Prepares all the tests that need big amounts of data although.
        TODO: Create randomized big data arrays.
        """
        pass


if __name__ == "__main__":
    unittest.main()
