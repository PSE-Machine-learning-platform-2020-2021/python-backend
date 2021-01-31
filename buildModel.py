import sys

import numpy as np
import pandas as pd
import tsfresh
from sklearn.ensemble import RandomForestClassifier
from sklearn.neighbors import KNeighborsClassifier
from sklearn.neural_network import MLPClassifier
from sklearn.preprocessing import Normalizer, MinMaxScaler, QuantileTransformer, RobustScaler, StandardScaler
from sklearn.svm import SVC
from tqdm import tqdm
from tsfresh.feature_extraction import ComprehensiveFCParameters


def select_features(features_from_outside):
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
    return [features_available[x] for x in features_from_outside]


def choose_scaler(name: str):
    """
    This method acts as a switch over the scaler type and creates out of a given type name a scaler.
    :param name: The name of the scaler type as of Preprocessing enum in TypeScript
    :return: An Scaler object matching the type of the passed type name
    """
    return {
        'MIN_MAX': MinMaxScaler(),
        'NORMALIZER': Normalizer(),
        'QUANTILE_TRANSFORMER': QuantileTransformer(),
        'ROBUST_SCALER': RobustScaler(),
        'STANDARD_SCALER': StandardScaler()
    }[name]


def choose_classifier(name: str):
    """
    This method acts as a switch over
    :param name:
    :return:
    """
    return {
        'MLP': MLPClassifier(),
        'RANDOM_FOREST': RandomForestClassifier(),
        'K_NEIGHBORS': KNeighborsClassifier(),
        'SVM': SVC()
    }[name]


def preprocess_data(data: pd.DataFrame, scaler, training_data=False):
    """
    This method performs the data preprocessing step.
    :param training_data: Indicates that this data is training data for an AI model, if set to True.
    :param data: The data to process
    :param scaler: The scaler to use for this task
    :return: Processed data.
    """
    scaler.fit(data)
    transformed_data = scaler.transform(data)
    if not training_data:
        return transformed_data
    else:
        return pd.DataFrame(transformed_data, columns=data.columns)


def train_classifier(x_axis_data, y_axis_data, classifier):
    """
    This method performs training on the classifier.
    :param x_axis_data: Transformed X training data
    :param y_axis_data: Untransformed Y training data
    :param classifier: The classifier to be trained.
    :return: Yet not fixed
    """
    classifier.fit(x_axis_data, y_axis_data)


def extract_features(data, features):
    window_size = 128
    sliding_step = 64
    X = []
    y = []
    for data_set in tqdm(data):
        df = pd.DataFrame(data_set)
        labels = np.array(df["label"].fillna(13))
        labels = np.where(labels > 6, 7, labels)
        df["label"] = labels
        # change the label  7 stand for all rest activity
        for sliding_index in range(0, df.shape[0] - window_size + 1, sliding_step):
            data_x = df.iloc[sliding_index:sliding_index + window_size, :6]
            data_y = df.iloc[sliding_index:sliding_index + window_size, 6].value_counts().index[0]
            X.append(data_x)
            y.append(data_y)
    settings = {key: ComprehensiveFCParameters()[key] for key in features}
    all_features = []
    for temp in tqdm(X):
        values = []
        values.extend(list(temp.mean().values))
        values.extend(list(temp.min().values))
        values.extend(list(temp.max().values))
        values.extend(list(temp.var().values))
        values.extend(list(temp.skew().values))
        values.extend(list(temp.kurtosis().values))
        all_features.append(values)
    all_features = pd.DataFrame(all_features)
    all_features["label"] = y
    temp = X[0].copy()
    temp["id"] = 1
    temp_feature = tsfresh.extract_features(temp,
                                            column_id="id",
                                            default_fc_parameters=settings,
                                            disable_progressbar=True)


if __name__ == "__main__":
    if len(sys.argv) < 2:
        raise IndexError

    scaler = choose_scaler(sys.argv[1])
    classifier = choose_classifier(sys.argv[2])

