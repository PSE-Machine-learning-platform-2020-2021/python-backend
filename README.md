# python-backend

##Coding Rules
+ After each comma, there has to be 1 space.
+ Indents are done in steps of 4 spaces.
+ Use single-quoted strings only for keys in dict literals.
+ All other strings are always enclosed by double quotes.
+ Line comments start with a # symbol.
+ All comments and Docstrings are written in English.

## Random implementation notes
### Database
Scalers and Classifiers shall be stored in pickle format.
It is ok as it is the easiest working way to get this done.\
For loading, use `pickle.load(<file-like object>)`
For storing, use `pickle.dump(<obj>, <file-like object>)`
Or, if easier, use both functions with additional s at function's name end to perform actions in script on byte object.
This might be necessary in order to store those object into a database.

### Note for the PHP programmer who will do the environment script for this script:
You must do first of all the following:

    ob_start();
    header("HTTP/1.1 200 OK");
    header('Connection: close');
    header('Content-Length: '.ob_get_length());
    ob_end_flush();
    ob_flush();
    flush();
    
After that you may start the python code via `xec("python buildModel.py", $output);`.
In this place `$output` will be filled with the program output that should be exactly one line containing the id of the 
ai model built.

This id is then to be sent via email to the user so he can deliver the model.   