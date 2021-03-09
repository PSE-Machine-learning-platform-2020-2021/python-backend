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

    def get_value(self, section: str, key: str) -> str:
        """
        Returns the value corresponding to key from section if present, else None.

        If the section does not exist, an Error is raised.

        :param section: The section to search in for key.
        :param key: The key, behind which is the desired value.
        :return: If section and key exist, the desired value is returned, else None.
        """
        return self.config.get(section, key, fallback=None)

    def get_values(self, section) -> dict[str, str]:
        """
        Returns all values associated with their keys which are in section.

        Raises an Error if section does not exist
        :param section: The desired section
        :return: A dict containing key-value pairs as the config file does or an error, if the section does not exist.
        """
        section = self.config.items(section)
        return dict(section)
