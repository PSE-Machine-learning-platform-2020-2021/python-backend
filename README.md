# python-backend

##Coding Rules
+ After each comma, there has to be 1 space.
+ Indents are done in steps of 4 spaces.
+ Use single-quoted strings only for keys in dict literals.
+ All other strings are always enclosed by double quotes.
+ Line comments start with a # symbol.
+ All comments and Docstrings are written in English.

## Random notes for storing and formats
All data sets need to be stored in an array looking like this: 

    ┌─[data columns]─┐|label
    1,1 |  ...  | 1,n |  X
    ... |  ...  | ... | ...
    n,1 |  ...  | n,n |  Y
     
First n columns are those containing the actual data and the last one contains a label connected to this data point.
The label column _**MUST NOT**_ contain negative numbers! I use them internally for labelling unlabeled areas.
Additionally we need some data about record frequency, as well as a dictionary
associating names to the label numbers in the label column in the data set. 

## Random implementation notes
Everything here is so to say ToDo
### Database
The Scaler Table needs to meet following requirements:

    name = scalers
    columns = Id, Scaler, ScalerType, Features, DataSets[, optional further columns]
    
The Classifier Table needs to look like this:

    name = classifiers
    columns = Id, Classifier, Scaler[, optional further columns]

### Note for the PHP programmer who will do the environment script for this script:
You must do first of all the following:

    ob_start();
    header("HTTP/1.1 200 OK");
    header('Connection: close');
    header('Content-Length: '.ob_get_length());
    ob_end_flush();
    ob_flush();
    flush();
    
After that you may start the python code via `exec("python buildModel.py", $output);`.
In this place `$output` will be filled with the program output that should be exactly one line containing the id of the 
ai model built.

This id is then to be sent via email to the user so he can deliver the model.   