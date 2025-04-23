# BytePattern Readme

## Directory Structure Explained

* __/app__

	This is the base application directory. All application related classes should go here. It must be present for every app using the framework.
	However, this is NOT the directory to place static files that need to be publicly exposed to the world.

    * __/app/client__

        The application should use this directory for files that need to be processed and sent down to the client.

    * __/app/server__

        The application should use this directory for any server-side PHP class files used by the application.

        * __/app/server/lang__

            The application should use this directory for server-side application localization files stored in XML format. This is
            different from the client-side localization in the fact it's meant to be used by the server to return to the client
            once per request. Anything intended to be changed on the client dynamically should use the client-side localization.

        * __/app/server/model__

            The application should use this directory for the data model classes used as the data access layer.

        * __/app/server/service__

            ...

    * __/app/style__

        ...

* __/dev__

    Files used during the development process that are not required to be present in production environments. This will typically include
    files from Node.js and NPM and miscellaneous configuration files and commands to work with them. 

* __/file__

    Downloadable files should be stored here. The reason they should not be in /pub is twofold: first files in this folder will not be
    cached and therefore can be updated without having to release a new version of the site, and second they are not public by default
    so this would be the best spot to place files that require authentication or tracking prior to downloading as well.

* __/log__

    Folder for PHP and web server log files.

* __/pub__

    Public folder, the web server's document root. Anything in here besides index.php will be served directly to the end user with no processing.
    If you want to store items such as images or SWFs, this would be the spot for it.

    * /pub/index.php

        This is the base stub/loader file that all requests must filter through. This is the main entry point of it all. You must include
        this file for every last thing you ever intend to do with the system.

* __/sys__

    This is the base system directory. All system related files and plugins should go here. Doing this will also allow for remote (over HTTP,
    symlink, etc.) invocation should the need arise to install one base system for more than one site and it is more secure than having these
    files in /pub. The developer is not expected to maintain files here since a system upgrade may wipe out pretty much everything.

    * __/sys/cache__

        The system will store cacheable files here.

    * __/sys/data__

        Database and data store related classes and files used by the system.

    * __/sys/extern__

        System modules / plug-ins / extensions will be stored here (shopping cart, cms, etc.) as a phar or PHP file.

    * __/sys/lang__

        The system will use this directory to load resources for the current language. Being used by the system directly this
        will only come into play for server-side items such as server-based error messages.


* __/util__

    Utility folder for PHP CLI scripts that provide supplementary functionality required for development; however, they are never intended to be served over the web.

* __/config.json__

    Server-side configuration file for system-wide settings in JSON format.

* __/setup.md__

    Useful setup instructions for getting BytePattern up and running.

* __/readme.md__

    This file.