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
For loading, use pickle.load(<file-like object>)
For storing, use pickle.dump(<obj>, <file-like object>)
Or, if easier, use both functions with additional s at function's name end to perform actions in script on byte object.
This might be necessary in order to store those object into a database.