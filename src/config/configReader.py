# coding=utf-8
"""
This file contains the class ConfigReader, an adaption of built-in ConfigParser
"""
import os
from configparser import ConfigParser


# noinspection PyPep8Naming
class ConfigReader:
    """
    This class is an adaption of built-in ConfigParser class.
    It bundles together some functionality to fit our needs.
    """

    CONFIG_FILE = os.path.dirname(os.path.realpath(__file__)) + os.sep + "config.ini"

    def __init__(self, *, alternate_config_file=None):
        self.config = ConfigParser()
        if alternate_config_file is None:
            self.config.read(self.CONFIG_FILE)
        else:
            self.config.read(alternate_config_file)

    def get_value(self, section: str, key: str):
        """

        :param section:
        :param key:
        :return:
        """
        return self.config.get(section, key, fallback=None)

    def get_values(self, section):
        """

        :param section:
        :return:
        """
        section = self.config.items(section)
        return dict(section)
