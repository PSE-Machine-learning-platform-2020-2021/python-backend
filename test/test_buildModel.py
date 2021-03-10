# coding=utf-8
"""
This file contains all unit tests for buildModel.py
"""
import io
import random
import unittest
from contextlib import redirect_stdout
from pickle import load
from unittest import TestCase

import pandas as pd

from sklearn.dummy import DummyClassifier
from sklearn.impute import SimpleImputer
from sklearn.neural_network import MLPClassifier
from sklearn.preprocessing import StandardScaler, Normalizer

from src.buildModel import buildModel

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
    "sensors": [
        "AccelarationSensor", 
        "Microphone"
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
                                                             sensors=["AccelarationSensor", "Microphone"],
                                                             features=["<Feature#1>", "<...>", "<Feature#n>"],
                                                             scaler="<Scaler>", classifier="<Classifier>",
                                                             trainingDataPercentage=0.8, slidingWindowSize=128,
                                                             slidingWindowStep=64))

    def test_choose_features(self):
        result = buildModel.choose_features(['MIN', 'ENERGY', 'FOURIER_TRANSFORM', 'Bullshit'])
        self.assertCountEqual(result, ["minimum", "abs_energy"])

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

    def test_create_time_slices_random_legal_both(self):
        result = buildModel.create_time_slices([self.data], self.imputator, self.chunk, self.step)
        self.assertEqual(len(result), 2)
        self.assertEqual(len(result[0]), self.length)
        self.assertEqual(len(result[0]), len(result[1]))
        self.assertLessEqual(len(result[0][0]), self.chunk)

    def test_create_time_slices_illegal_values(self):
        self.assertRaises(ValueError, buildModel.create_time_slices, [self.data], self.imputator, 0)
        self.assertRaises(ValueError, buildModel.create_time_slices, [self.data], None, self.chunk, self.chunk + 1)
        self.assertRaises(ValueError, buildModel.create_time_slices, [self.data], self.imputator, step=0)

    def test_extract_features(self):
        buildModel.extract_features(["minimum", "maximum", "variance", "mean"], [self.data.iloc[:1024, :-1]],
                                    [1], self.imputator)
        self.assertTrue(True)

    def test_partition_data(self):
        result = buildModel.partition_data(self.data)
        self.assertEqual(result[0].shape[1] + 1, self.columns)
        self.assertEqual(result[0].shape[0] + result[2].shape[0], self.data.shape[0])
        self.assertAlmostEqual(result[0].shape[0] / self.data.shape[0], 0.8, 1)

        percentage = random.random()
        result = buildModel.partition_data(self.data, percentage)
        self.assertEqual(result[0].shape[1] + 1, self.columns)
        self.assertEqual(result[0].shape[0] + result[2].shape[0], self.data.shape[0])
        self.assertAlmostEqual(result[0].shape[0] / self.data.shape[0], percentage, 1)

        self.assertRaises(ValueError, buildModel.partition_data, None)
        self.assertRaises(ValueError, buildModel.partition_data, self.data, 0)
        self.assertRaises(ValueError, buildModel.partition_data, self.data, 1)
        self.assertRaises(ValueError, buildModel.partition_data, self.data, -1)
        self.assertRaises(ValueError, buildModel.partition_data, self.data, 42)

    def test_preprocess_data(self):
        result = buildModel.preprocess_data(self.data.iloc[:, :-1], self.scaler)
        self.assertIsNotNone(result)
        self.assertEqual(type(result), pd.DataFrame)

    def test_train_classifier(self):
        self.assertIsNone(buildModel.train_classifier(self.data.iloc[:, :-1],
                                                      self.data["label"],
                                                      self.classifier))

    def test_notify_server(self):
        f = io.StringIO()
        with redirect_stdout(f):
            buildModel.notify_server(713666)
            output = f.getvalue().strip()
        self.assertEqual("713666", output)

    def setUp(self) -> None:
        """
        Prepares all the tests that need big amounts of data although.
        """
        data = pd.read_csv("exp12_user06.csv")
        self.data = pd.DataFrame(data).fillna(-1)
        self.chunk = random.randint(1, 2 ** 8)
        self.step = random.randint(1, self.chunk)
        self.length = 1
        self.imputator = SimpleImputer()
        while self.length * self.step < self.data.shape[0] - self.chunk:
            self.length += 1
        self.scaler = Normalizer()
        self.classifier = MLPClassifier()
        self.columns = self.data.shape[1]
        self._file = open("temp", "rb")
        self.extracted_data = load(self._file)

    def tearDown(self) -> None:
        """
        Clears up all resources.
        """
        self._file.close()
        del self.data, self.chunk, self.step, self.length, self.scaler, self.classifier, self.columns
        del self.extracted_data, self.imputator, self._file


if __name__ == "__main__":
    unittest.main()
