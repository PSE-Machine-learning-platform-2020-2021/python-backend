# coding=utf-8
"""
Yexu's workshop copied for learning reasons.
"""
import os
import pickle

import numpy as np
import pandas as pd
import tsfresh
from sklearn.ensemble import RandomForestClassifier
from sklearn.impute import SimpleImputer
from sklearn.metrics import accuracy_score, classification_report
from sklearn.neighbors import KNeighborsClassifier
from sklearn.preprocessing import RobustScaler
from sklearn.svm import SVC
from tsfresh.feature_extraction import ComprehensiveFCParameters

if __name__ == "__main__":
    data_path = "../../../Entwurf/activity_recognition/Workshop_Data"
    csv_file = "exp01_user01.csv"

    label_dict: dict[int, str] = {1: "WALKING", 2: "WALKING_UPSTAIRS", 3: "WALKING_DOWNSTAIRS", 4: "SITTING",
                                  5: "STANDING", 6: "LYING", 7: "OTHER"}

    # # Data Preparation
    window_size = 128
    sliding_step = 64

    csv_list = os.listdir(data_path)
    X = []
    y = []
    for csv in csv_list:
        df = pd.read_csv(os.path.join(data_path, csv))
        labels = np.array(df["label"].fillna(13))
        first_imputer = SimpleImputer()
        first_imputer.fit(df)
        df = pd.DataFrame(first_imputer.transform(df), columns=df.columns, index=df.index)
        # change the label 7 stand for all rest activity
        labels = np.where(labels > 6, 7, labels)
        df["label"] = labels
        for sliding_index in range(0, df.shape[0] - window_size + 1, sliding_step):
            data_x = df.iloc[sliding_index:sliding_index + window_size, :-1]
            data_y = df.iloc[sliding_index:sliding_index + window_size, -1].value_counts().index[0]
            # .value_counts collects which value occurs how often and sorts that descending by occurrence, so that
            # .index[0] just takes that label which occurs most often.
            X.append(data_x)
            y.append(data_y)

    # # Data Transformation

    # ## "Feature Extraction"

    # Select the features to be extracted.
    try:
        # in this case we already extracted the features once and could store them in a file.
        with open("temp", "rb") as file:
            all_features = pickle.load(file)
    except OSError:
        # in this case we still have to do this.
        feature_to_extract = ["mean", "variance", "skewness", "kurtosis", "maximum", "minimum",
                              "longest_strike_below_mean", "longest_strike_above_mean", "sample_entropy",
                              "ar_coefficient", "linear_trend_timewise", "spkt_welch_density"]
        settings = {key: ComprehensiveFCParameters()[key] for key in feature_to_extract}
        results = []
        for j in range(len(X)):
            X[j]["id"] = j
            data_feature = tsfresh.extract_features(X[j], column_id="id", default_fc_parameters=settings)
            results.append(data_feature)

        #    all_features = pd.read_csv(os.path.join(data_path, "all_feature.csv"))

        all_features = pd.concat(results)
        all_features.replace([np.inf, -np.inf], np.NaN)
        with open("temp", "wb") as file:
            pickle.dump(all_features, file)

    second_imputer = SimpleImputer(missing_values=np.NaN)
    second_imputer.fit(all_features)
    all_features = second_imputer.transform(all_features)
    all_features["label"] = y
    # ## Train Test Split
    train_x = all_features.iloc[:int(all_features.shape[0] * 0.8), :-1]
    train_y = all_features.iloc[:int(all_features.shape[0] * 0.8), -1]
    test_x = all_features.iloc[int(all_features.shape[0] * 0.8):, :-1]
    test_y = all_features.iloc[int(all_features.shape[0] * 0.8):, -1]

    # ## Normalization

    train_x.describe()

    scaler = RobustScaler()
    scaler.fit(train_x)
    trans_train_x = scaler.transform(train_x)
    trans_test_x = scaler.transform(test_x)

    trans_train_x = pd.DataFrame(trans_train_x, columns=train_x.columns)
    trans_train_x = pd.DataFrame(trans_train_x)

    trans_train_x.describe()

    # # Model Training and Validation

    clf = RandomForestClassifier()
    clf.fit(trans_train_x, train_y)

    prediction = clf.predict(trans_train_x)
    print("accuracy_score train :", accuracy_score(train_y, prediction))

    prediction = clf.predict(trans_test_x)
    print("accuracy_score test :", accuracy_score(test_y, prediction))

    print(classification_report(test_y, prediction))

    clf = SVC(C=20, kernel="linear")
    clf.fit(trans_train_x, train_y)

    prediction = clf.predict(trans_train_x)
    print("accuracy_score train :", accuracy_score(train_y, prediction))

    prediction = clf.predict(trans_test_x)
    print("accuracy_score test :", accuracy_score(test_y, prediction))

    clf = KNeighborsClassifier()
    clf.fit(trans_train_x, train_y)
    prediction = clf.predict(trans_train_x)
    print("accuracy_score train :", accuracy_score(train_y, prediction))
    prediction = clf.predict(trans_test_x)
    print("accuracy_score test :", accuracy_score(test_y, prediction))
