<?php
//
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author:  Alan Knowles <alan@akbkhome.com>
// | Original Author: Wolfram Kriesing <wolfram@kriesing.de>             |
// +----------------------------------------------------------------------+
//
 
/**
*   @package    HTML_Template_Flexy
*/

require_once 'PEAR.php'; 
/*
* Global variable - used to store active options when compiling a template.
*/
$GLOBALS['_HTML_TEMPLATE_FLEXY'] = array(); 

/**
* A Flexible Template engine - based on simpletemplate  
*
* @abstract Long Description
*  This is  a rip of of Wolfram's Simple Template class - heavily simplified,
*  with a modified filter loading mechanism.
*  notebly 
*       - no xml config stuff
*       - simplified filters - so you just tell it which filter (classes) to use, 
*            not individual methods
*       - a smarty like tag library, 
*       - heavily focused on displaying objects as pages.
* (so you can document your tags.)
*
* @version    $Id$
*/
class HTML_Template_Flexy  
{

    /*
    *   @var    array   $options    the options for initializing the template class
    */
    var $options = array(   'compileDir'    =>  '',      // by default its always the same one as where the template lies in, this might not be desired
                            'templateDir'   =>  '',
                            'forceCompile'  =>  false,  // only suggested for debugging
                            'filters'       => array(),
                            'debug'         => false,
                            'locale'        => 'en',
                            'useLegacy'     => false,  // use old preg_replace code
                            'nonHTML'       => false,  // dont parse HTML tags (eg. email templates)
                            'allowPHP'      => false   // allow PHP in template
                        );

    
    
        
    /**
    * emailBoundary  - to use put {this.emailBoundary} in template
    *
    * @var string
    * @access public
    */
    var $emailBoundary;
    /**
    * emaildate - to use put {this.emaildate} in template
    *
    * @var string
    * @access public
    */
    var $emaildate;
    /**
    * The compiled template filename (Full path)
    *
    * @var string
    * @access public
    */
    var $compiledTemplate;
    
    /**
    * The getTextStrings Filename
    *
    * @var string
    * @access public
    */
    var $gettextStringsFile;
    /**
    * The serialized elements array file.
    *
    * @var string
    * @access public
    */
    var $elementsFile;
    
     
    /**
    * Array of HTML_elements to merge with form
    * 
    *
    * @var array of  HTML_Template_Flexy_Elements
    * @access public
    */
    var $elements = array();
    /**
    *   Constructor 
    *
    *   Initializes the Template engine, for each instance, accepts options or
    *   reads from PEAR::getStaticProperty('HTML_Template_Flexy','options');
    *
    *   @access public
    *   @param    array    $options (Optional)
    */
    

    
    function HTML_Template_Flexy( $options=array() )
    {
        $baseoptions = &PEAR::getStaticProperty('HTML_Template_Flexy','options');
       
        if ($baseoptions ) {
            foreach( $baseoptions as  $key=>$aOption )  {
                $this->options[$key] = $aOption;
            }
        }
        
        foreach( $options as $key=>$aOption )  {
           $this->options[$key] = $aOption;
        }
        
        $filters = $this->options['filters'];
        if (is_string($filters)) {
            $this->options['filters']= explode(',',$filters);
        }
        
        if(!@is_dir($this->options['compileDir']) )
            return new PEAR_Error('The compile-directory doesnt exist yet!');
    }

    /**
    *   Outputs an object as $t 
    *
    *   for example the using simpletags the object's variable $t->test
    *   would map to {t.test}
    *
    *   maps ** these are all depreciated and should not be used!
    *   the area only available if useLegacy is set.
    *   $t->o_*           maps to {o.*}  // used for output
    *   $t->input         maps to {i.*} // used for input
    *   $t->modules[xxxx] maps to {m.xxxx} // used for modules
    *   $t->config        maps to {c.*}  // used for config
    *   PEAR::getStaticProperty('Auth','singleton') maps to  {a.*}
    *   {email_boundary} // for email boundaries  
    *   {email_date}    // for email dates
    *
    *
    *   @version    01/12/14
    *   @access     public
    *   @author     Alan Knowles
    *   @param    object object to output as $t
    *   @return     none
    */
    
    
    function outputObject(&$t,$elements=array()) 
    {
        $options = PEAR::getStaticProperty('HTML_Template_Flexy','options');
        if (@$options['debug']) {
            echo "output $this->compiledTemplate<BR>";
            
        }
        
        $this->emailBoundary = md5("FlexyMail".microtime());
        $this->emailDate = date("D j M Y G:i:s O");
        
        if ($this->options['useLegacy']) {
            
            $m = new StdClass;
            // compile modules
             
            /* these are to be depreciated !!!*/
            
            
            $c = & $t->config;
            if (@$t->input) {
                $i = & $t->input;
            }
            $a = &PEAR::getStaticProperty('Auth','singleton');
            
            /* expose o_ as $o */
            $o = new StdClass;
            foreach (get_object_vars($t) as $k=>$v) {
                if ($k{0} != "o") {
                    continue;
                }
                if ($k{1} != "_") {
                    continue;
                }
                $kk = substr($k,2);
                $o->$kk =& $t->$k;
            }
            
            
            /* usefull stuff for doing emails in Template Flexy */
            $email_boundary = $this->emailBoundary;
            $email_date = $this->emailDate;
        }
        // this may disappear later it's a BC fudge to try and deal with 
        // the old way of setting $this->elements to be merged.
        // the correct behavior is to use the extra field in outputObject.
       
        if (count($this->elements) && !count($elements)) {
            $elements = $this->elements;
        }
       
        $this->elements = $this->getElements();
        
        // overlay elements..
        
        foreach($elements as $k=>$v) {
            if (!$v) {
                unset($this->elements[$k]);
            }
            $this->elements[$k] = $this->mergeElement($this->elements[$k] ,$v);
        }
        
      
        
         // we use PHP's error handler to hide errors in the template.
        // this may be removed later, or replace with
        // options['strict'] - so you have to declare
        // all variables
        
        
        $_error_reporting = false;
        if (!$options['debug']) {
            $_error_reporting = error_reporting(E_ALL ^ E_NOTICE);
        }
        if (!is_readable($this->compiledTemplate)) {
              PEAR::raiseError( "Could not open the template: <b>'{$this->compiledTemplate}'</b><BR>".
                            "Please check the file permisons on the directory and file ",
                            null, PEAR_ERROR_DIE);
        }
        
        
        
        include($this->compiledTemplate);
        if ($_error_reporting !== false) {
            error_reporting($_error_reporting);
        }
    }
    /**
    *   Outputs an object as $t, buffers the result and returns it.
    *
    *   See outputObject($t) for more details.
    *
    *   @version    01/12/14
    *   @access     public
    *   @author     Alan Knowles
    *   @param      object object to output as $t
    *   @return     string - result
    */
    function &bufferedOutputObject(&$t,$elements=array()) 
    {
        ob_start();
        $this->outputObject($t);
        $data = ob_get_contents();
        ob_end_clean();
        return $data;
    }
    /**
    * static version which does new, compile and output all in one go.
    *
    *   See outputObject($t) for more details.
    *
    *   @version    01/12/14
    *   @access     public
    *   @author     Alan Knowles
    *   @param      object object to output as $t
    *   @param      filename of template
    *   @return     string - result
    */
    function &staticQuickTemplate($file,&$t) 
    {
        $template = new HTML_Template_Flexy;
        $template->compile($file);
        $template->outputObject($t);
    }
    
      
 
 
    /**
    *   compile the template
    *
    *   @access     public
    *   @version    01/12/03
    *   @author     Wolfram Kriesing <wolfram@kriesing.de>
    *   @param      string  $file   relative to the 'templateDir' which you set when calling the constructor
    *   @param      boolean $fixForMail - replace ?>\n with ?>\n\n
    *   @return
    */
    
    
    
    function compile( $file )
    {
        if (!$file) {
            PEAR::raiseError('HTML_Template_Flexy::compile no file selected',null,PEAR_ERROR_DIE);
        }
        if (!@$this->options['locale']) {
            $this->options['locale']='en';
        }
        // on windows the base directory will be C:!
        // so you have to hard code the path (no relatives on windows
        if (DIRECTORY_SEPARATOR == "/") {
            // if the compileDir doesnt start with a / then its under the template dir    
            if ( $this->options['compileDir']{0} !=  DIRECTORY_SEPARATOR ) {
                $this->options['compileDir'] =  $this->options['templateDir'].'/'.$this->options['compileDir'];
            }
        }

        // remove the slash if there is one in front, just to be clean
        if ( $file{0} == DIRECTORY_SEPARATOR  ) {
            $file = substr($file,1);
        }

        $compileDest = $this->options['compileDir'];
        if ( !@is_dir($compileDest) ) {               // check if the compile dir has been created
            PEAR::raiseError(   "'compileDir' could not be accessed<br>".
                                "Please create the 'compileDir' which is: <b>'$compileDest'</b><br>2. give write-rights to it",
                                null, PEAR_ERROR_DIE);
        }

    

        $directory = dirname( $file );
        $filename = basename($file);

        // extract dirname to create directori(es) in compileDir in case they dont exist yet
        // we just keep the directory structure as the application uses it, so we dont get into conflict with names
        // i dont see no reason for hashing the directories or the filenames
        if( $directory!='.' )  { // it is '.' also if no dir is given
            $path = explode(DIRECTORY_SEPARATOR ,$directory);
            foreach( $path as $aDir ) {
                $compileDest = $compileDest. DIRECTORY_SEPARATOR . $aDir;
                if( !@is_dir($compileDest) ) {
                    umask(0000);                        // make that the users of this group (mostly 'nogroup') can erase the compiled templates too
                    if( !@mkdir($compileDest,0770) ) {
                        PEAR::raiseError(   "couldn't make directory: <b>'$aDir'</b> under <b>'".$this->options['compileDir']."'</b><br>".
                                            "Please give write permission to the 'compileDir', so HTML_Template_Flexy can create directories inside",
                                             null, PEAR_ERROR_DIE);
                    }
                }
            }
        }
        
        /* 
        
            incomming file looks like xxxxxxxx.yyyy
            if xxxxxxxx.{locale}.yyy exists - use that...
        */
        $parts = array();
        if (preg_match('/(.*)(\.[a-z]+)$/i',$file,$parts)) {
            $newfile = $parts[1].'.'.$this->options['locale'] .$parts[2];
            if (@file_exists($this->options['templateDir']. DIRECTORY_SEPARATOR .$newfile)) {
                $file = $newfile;
            }
        }
        
        
        $this->currentTemplate = $this->options['templateDir'].DIRECTORY_SEPARATOR .$file;
        
        if( !@file_exists($this->currentTemplate ))  {
            // check if the compile dir has been created
            PEAR::raiseError("Template {$this->currentTemplate} does not exist<br>",  null, PEAR_ERROR_DIE);
        }
         
 
        
        
        $this->compiledTemplate    = $compileDest.DIRECTORY_SEPARATOR .$filename.'.'.$this->options['locale'].'.php';
        $this->getTextStringsFile  = $compileDest.DIRECTORY_SEPARATOR .$filename.'.gettext.serial';
        $this->elementsFile        = $compileDest.DIRECTORY_SEPARATOR .$filename.'.elements.serial';
        
        
        
        $recompile = false;
        if( @$this->options['forceCompile'] ) {
            $recompile = true;
        }

        if( $recompile==false )  {                    // if recompile is true dont bother to check if template has changed
            if( !$this->isUpToDate() ) {                 // check if the template has changed
                $recompile = true;
            }
        }
        
        
        
        if(! $recompile )  {             // or any of the config files
            return true;
        }
        
        if( !is_writeable($compileDest)) {
            PEAR::raiseError(   "can not write to 'compileDir', which is <b>'$compileDest'</b><br>".
                            "Please give write and enter-rights to it",
                            null, PEAR_ERROR_DIE);
        }
        
        // compile it..
        
        require_once 'HTML/Template/Flexy/Compiler.php';
        $compiler = HTML_Template_Flexy_Compiler::factory($this->options);
        return $compiler->compile($this);
        
        //return $this->$method();
        
    }

     /**
    *  compiles all templates
    *  used for offline batch compilation (eg. if your server doesnt have write access to the filesystem)
    *
    *   @access     public
    *   @author     Alan Knowles <alan@akbkhome.com>
    *
    */
    function compileAll($dir = '',$regex='/.html$/')
    {
        
        $base =  $this->options['templateDir'];
        $dh = opendir($base . DIRECTORY_SEPARATOR  . $dir);
        while (($name = readdir($dh)) !== false) {
            if (!$name) {  // empty!?
                continue;
            }
            if ($name{0} == '.') {
                continue;
            }
             
            if (is_dir($base . DIRECTORY_SEPARATOR  . $dir . DIRECTORY_SEPARATOR  . $name)) {
                $this->compileAll($dir . DIRECTORY_SEPARATOR  . $name,$regex);
                continue;
            }
            
            if (!preg_match($regex,$name)) {
                continue;
            }
            echo "Compiling $dir". DIRECTORY_SEPARATOR  . "$name \n";
            $this->compile($dir . DIRECTORY_SEPARATOR  . $name);
        }
        
    }
    /**
    *   checks if the compiled template is still up to date
    *
    *   @access     private
    *   @version    01/12/03
    *   @author     Wolfram Kriesing <wolfram@kriesing.de>
    *   @param      string      $fileToCheckAgainst if given this file is checked if it is newer than the compiled template
    *                                               this is useful if for example only an xml-config file has changed but not the
    *                                               template itself
    *   @return     boolean     true if it is still up to date
    */
    function isUpToDate( $fileToCheckAgainst='' )
    {
        if( $fileToCheckAgainst == '' ) {
            $checkFile = $this->currentTemplate;
        } else {
            $checkFile = $fileToCheckAgainst;
        }

        if( !file_exists( $this->compiledTemplate ) ||
            filemtime( $checkFile ) != filemtime( $this->compiledTemplate )
          ) {
            return false;
        }

        return true;
    }

      
    
     
    
    
    
     /**
    *   if debugging is on, print the debug info to the screen
    *
    *   @access     public
    *   @author     Alan Knowles <alan@akbkhome.com>
    *   @param      string  $string       output to display
    *   @return     none
    */
    function debug($string) 
    {  
        
        if (!$this->options['debug']) {
            return;
        }
        echo "<PRE><B>FLEXY DEBUG:</B> $string</PRE>";
        
    }
     
    /**
     * A general Utility method that merges HTML_Template_Flexy_Elements
     
     *
     * @param    HTML_Template_Flexy_Element   $original  (eg. from getElements())
     * @param    HTML_Template_Flexy_Element   $new (with data to replace/merge)
     * @return   HTML_Template_Flexy_Element   the combined/merged data.
     * @access   public
     */
     
    function mergeElement($original,$new)
    {
     
        
        // changing tags.. - should this be valid?
        // hidden is one use of this....
        if (!$original) {
            return $new;
        }
        if ($new->tag && ($new->tag != $original->tag)) {
            $original->tag = $new->tag;
        }
        
        if ($new->override !== false) {
            $original->override = $new->override;
        }
        //if $from is not an object:
        // then it's a value set....
        
        if (count($new->children)) {
            //echo "<PRE> COPY CHILDREN"; print_r($from->children);
            $original->children = $new->children;
        }
        
        foreach ($new->attributes as $key => $value) {
            $original->attributes[$key] = $value;
        }
        $original->prefix = $new->prefix;
        $original->suffix = $new->suffix;  
        if ($new->value !== null) {
            //echo "<PRE>";print_r($original);
            $original->setValue($new->value);
        } 
       
        return $original;
        
    }  
     
     
    /**
    * Get an array of elements from the template
    *
    * All form elements and anything marked as dynamic are converted in to elements
    * (simliar to XML_Tree_Node) - you can then modify or merge them at the output stage
 
    *
    * @return   array   of HTML_Template_Flexy_Element sDescription
    * @access   public
    */
    
    function getElements() {
        if ($this->elementsFile && file_exists($this->elementsFile)) {
            require_once 'HTML/Template/Flexy/Element.php';
            return unserialize(file_get_contents($this->elementsFile));
        }
        return array();
    }
    
    
}
?>