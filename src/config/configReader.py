# coding=utf-8
"""
This file contains the class ConfigReader, an adaption of built-in ConfigParser
"""
from configparser import ConfigParser


def constant(f):
    """
    This method defines an annotation for a method to become a final variable as known from Java.
    :param f: the method to become a constant
    :return: a property with a getter and a setter.
    """
    def f_set():
        """
        This method is the setter for the new constant.
        It prevents a variable from being set to a new value, by raising a TypeError when called.
        """
        raise TypeError

    def f_get():
        """
        This method is the getter for the new constant.
        It just does, what a getter does.
        :return: the current value of constant f.
        """
        return f()

    return property(f_get, f_set)


# noinspection PyPep8Naming
class ConfigReader:
    """
    This class is an adaption of built-in ConfigParser class.
    It bundles together some functionality to fit our needs.
    """

    # noinspection PyPep8Naming
    @constant
    def CONFIG_FILE(self):
        """

        :return:
        """
        return "config.ini"

    def __init__(self, *, alternate_config_file=None):
        self.config = ConfigParser()
        if alternate_config_file is None:
            self.config.read(self.CONFIG_FILE)
        else:
            self.config.read(self.CONFIG_FILE)

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
