from configparser import ConfigParser


def constant(f):
    def fset(self, value):
        raise TypeError

    def fget(self):
        return f()

    return property(fget, fset)


class ConfigReader:
    @constant
    def CONFIG_FILE(self):
        return "config.ini"

    def __init__(self, *, alternate_config_file=None):
        self.config = ConfigParser()
        if alternate_config_file is None:
            self.config.read(self.CONFIG_FILE)
        else:
            self.config.read(self.CONFIG_FILE)

    def get_value(self, section: str, key: str):
        return self.config.get(section, key, fallback=None)

    def get_values(self, section):
        section = self.config.items(section)
        return dict(section)
