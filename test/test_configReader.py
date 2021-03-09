# coding=utf-8
"""
Unit tests for config reader class
"""
import configparser
import os
import tempfile
from unittest import TestCase

from src.config.configReader import ConfigReader


class ConfigReaderTest(TestCase):
    def setUp(self) -> None:
        """
        This file creates the config reader object.
        Also it creates a temporary config file in ini format so we can test
        """
        self.file = tempfile.NamedTemporaryFile(delete=False, mode="w+")
        self.file.write(
            """[DB]
            host = localhost
            user = Not yet known
            password = Let's see, 
                if we can hide this
            database = This and that
            
            [USAR]
            # Hamburger Nahverkehr
            U = U1, U2, U3, U4
            S = S1/S11, S21/S2, S3/S31
            A = A1, A2, A3
            ; Irgendwie ist das schon keiner mehr - zumindest nicht so wirklich.
            R = Ganz viele
            
            [Test]
            XUL = 1
            te at = asdf kljöa asd 
                asdf asdf asdf asdf
            """
        )
        self.configParser = configparser.ConfigParser()
        self.file.seek(0)
        self.configParser.read_file(self.file)
        self.testedReader = ConfigReader(alternate_config_file=self.file.name)

    def tearDown(self) -> None:
        """
        This method removes the file by closing it, if it is still there.
        """
        if os.path.exists(self.file.name):
            self.file.close()
        self.configParser = None
        self.testedReader = None

    def test_get_value(self):
        result = self.testedReader.get_value("USAR", "U")
        self.assertEqual(result, self.configParser.get("USAR", "U"))
        self.assertIsNone(self.testedReader.get_value("USAR", "Bus"))
        self.assertIsNone(self.testedReader.get_value("QXC", "fail"))
        result = self.testedReader.get_value("Test", "te at")
        self.assertEqual(result, self.configParser.get("Test", "te at"))

    def test_get_values(self):
        self.fail()
