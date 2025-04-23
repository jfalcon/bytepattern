<?php

/*/
/ / --------------------------------------------------------------------------------------------------------------------
/ / anything in this will be executed automatically with every server request prior to application processing
/ / --------------------------------------------------------------------------------------------------------------------
/*/

namespace Application;

// the application subclass allows us to add application specific logic on a global basis
abstract class Application extends \System\Application
{
    // for debugging purposes, we do not prevent the direct echoing of data to STDOUT; however, it is bad practice
    // and should be avoided. you should use this render method instead as it will call what needs to be called to
    // get its groove thang on - as such when this method is called it will wipe out any existing output buffers
    public function render()
    {
        parent::getOutput()->setLink('/img/' . _c('id') . '/favicon.ico', 'icon');

        parent::getOutput()->setStyle('main');
        parent::getOutput()->setStyle('w2ui');

        parent::getOutput()->setScript('w2ui');
        parent::getOutput()->setScript('main');

        parent::render();
    }
}

?>
