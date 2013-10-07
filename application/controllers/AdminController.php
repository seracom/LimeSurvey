<?php
/*
* LimeSurvey
* Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
* All rights reserved.
* License: GNU/GPL License v2 or later, see LICENSE.php
* LimeSurvey is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*
*/

class AdminController extends LSYii_Controller
{
    public $lang = null;
    public $layout = false;
    protected $user_id = 0;

    /**
    * Initialises this controller, does some basic checks and setups
    *
    * @access protected
    * @return void
    */
    protected function _init()
    {
        parent::_init();
        App()->getComponent('bootstrap')->init();
        $updatelastcheck = getGlobalSetting('updatelastcheck');

        $this->_sessioncontrol();

        if (Yii::app()->getConfig('buildnumber') != "" && Yii::app()->getConfig('updatecheckperiod') > 0 && $updatelastcheck < dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", "-". Yii::app()->getConfig('updatecheckperiod')." days"))
            updateCheck();

        //unset(Yii::app()->session['FileManagerContext']);
        App()->getClientScript()->registerScriptFile(Yii::app()->getConfig('adminscripts') . "admin_core.js");
        $this->user_id = Yii::app()->user->getId();
        if (!Yii::app()->getConfig("surveyid")) {Yii::app()->setConfig("surveyid", returnGlobal('sid'));}         //SurveyID
        if (!Yii::app()->getConfig("ugid")) {Yii::app()->setConfig("ugid", returnGlobal('ugid'));}                //Usergroup-ID
        if (!Yii::app()->getConfig("gid")) {Yii::app()->setConfig("gid", returnGlobal('gid'));}                   //GroupID
        if (!Yii::app()->getConfig("qid")) {Yii::app()->setConfig("qid", returnGlobal('qid'));}                   //QuestionID
        if (!Yii::app()->getConfig("lid")) {Yii::app()->setConfig("lid", returnGlobal('lid'));}                   //LabelID
        if (!Yii::app()->getConfig("code")) {Yii::app()->setConfig("code", returnGlobal('code'));}                // ??
        if (!Yii::app()->getConfig("action")) {Yii::app()->setConfig("action", returnGlobal('action'));}          //Desired action
        if (!Yii::app()->getConfig("subaction")) {Yii::app()->setConfig("subaction", returnGlobal('subaction'));} //Desired subaction
        if (!Yii::app()->getConfig("editedaction")) {Yii::app()->setConfig("editedaction", returnGlobal('editedaction'));} // for html editor integration
    }

    /**
    * Shows a nice error message to the world
    *
    * @access public
    * @param string $message The error message
    * @param string|array $url URL. Either a string. Or array with keys url and title
    * @return void
    */
    public function error($message, $url = array())
    {
        $clang = $this->lang;

        $this->_getAdminHeader();
        $output = "<div class='messagebox ui-corner-all'>\n";
        $output .= '<div class="warningheader">'.$clang->gT('Error').'</div><br />'."\n";
        $output .= $message . '<br /><br />'."\n";
        if (!empty($url) && !is_array($url))
        {
            $title = $clang->gT('Back');
        }
        elseif (!empty($url['url']))
        {
            if (!empty($url['title']))
            {
                $title = $url['title'];
            }
            else
            {
                $title = $clang->gT('Back');
            }
            $url = $url['url'];
        }
        else
        {
            $title = $clang->gT('Main Admin Screen');
            $url = $this->createUrl('/admin');
        }
        $output .= '<input type="submit" value="'.$title.'" onclick=\'window.open("'.$url.'", "_top")\' /><br /><br />'."\n";
        $output .= '</div>'."\n";
        $output .= '</div>'."\n";
        echo $output;

        $this->_getAdminFooter('http://manual.limesurvey.org', $clang->gT('LimeSurvey online manual'));

        die;
    }
    /**
    * Load and set session vars
    *
    * @access protected
    * @return void
    */
    protected function _sessioncontrol()
    {
        Yii::import('application.libraries.Limesurvey_lang');
        // From personal settings
        if (Yii::app()->request->getPost('action') == 'savepersonalsettings') {
            if (Yii::app()->request->getPost('lang')=='auto')
            {
                $sLanguage= getBrowserLanguage();
            }
            else
            {
                $sLanguage=Yii::app()->request->getPost('lang');
            }
            Yii::app()->session['adminlang'] = $sLanguage;
        }

        if (empty(Yii::app()->session['adminlang']))
            Yii::app()->session["adminlang"] = Yii::app()->getConfig("defaultlang");

        global $clang; // Needed so EM can localize equation hints until a better solution is found
        $this->lang = $clang = new Limesurvey_lang(Yii::app()->session['adminlang']);
        Yii::app()->setLang($this->lang);

        if (!empty($this->user_id))
            $this->_GetSessionUserRights($this->user_id);
    }

    /**
    * Checks for action specific authorization and then executes an action
    *
    * @access public
    * @param string $action
    * @return bool
    */
    public function run($action)
    {
        // Check if the DB is up to date
        if (Yii::app()->db->schema->getTable('{{surveys}}'))
        {
            $usrow = getGlobalSetting('DBVersion');
            if ((int) $usrow < Yii::app()->getConfig('dbversionnumber') && $action != 'update' && $action != 'authentication')
                $this->redirect(array('/admin/update/sa/db'));
        }

        if ($action != "update" && $action != "db")
            if (empty($this->user_id) && $action != "authentication"  && $action != "remotecontrol")
            {
                if (!empty($action) && $action != 'index')
                    Yii::app()->session['redirect_after_login'] = $this->createUrl('/');

                App()->user->setReturnUrl(App()->request->requestUri);

                $this->redirect(array('/admin/authentication/sa/login'));
            }
            elseif (!empty($this->user_id)  && $action != "remotecontrol")
            {
                if (Yii::app()->session['session_hash'] != hash('sha256',getGlobalSetting('SessionName').Yii::app()->user->getName().Yii::app()->user->getId()))
                {
                    Yii::app()->session->clear();
                    Yii::app()->session->close();
                    $this->redirect(array('/admin/authentication/sa/login'));
                }
                
            }

            return parent::run($action);
    }

    /**
    * Routes all the actions to their respective places
    *
    * @access public
    * @return array
    */
    public function actions()
    {
        $actions = $this->getActionClasses();

        foreach ($actions as $action => $class)
        {
            $actions[$action] = "application.controllers.admin.{$class}";
        }

        return $actions;
    }

    public function getActionClasses()
    {
        return array(
        'assessments'      => 'assessments',
        'authentication'   => 'authentication',
        'checkintegrity'   => 'checkintegrity',
        'conditions'       => 'conditionsaction',
        'database'         => 'database',
        'dataentry'        => 'dataentry',
        'dumpdb'           => 'dumpdb',
        'emailtemplates'   => 'emailtemplates',
        'export'           => 'export',
        'expressions'      => 'expressions',
        'globalsettings'   => 'globalsettings',
        'htmleditor_pop'   => 'htmleditor_pop',
        'limereplacementfields' => 'limereplacementfields',
        'index'            => 'index',
        'labels'           => 'labels',
        'participants'     => 'participantsaction',
        'printablesurvey'  => 'printablesurvey',
        'questiongroups'    => 'questiongroups',
        'questions'         => 'questions',
        'quotas'           => 'quotas',
        'remotecontrol'    => 'remotecontrol',
        'responses'        => 'responses',
        'saved'            => 'saved',
        'statistics'       => 'statistics',
        'survey'           => 'surveyadmin',
        'surveypermission' => 'surveypermission',
        'user'             => 'useraction',
        'usergroups'       => 'usergroups',
        'templates'        => 'templates',
        'tokens'           => 'tokens',
        'translate'        => 'translate',
        'update'           => 'update',
        );
    }

    /**
    * Set Session User Rights
    *
    * @access public
    * @return void
    */
    public function _GetSessionUserRights($loginID)
    {
        $user = User::model()->findByPk($loginID);

        // SuperAdmins
        // * original superadmin with uid=1 unless manually changed and defined
        //   in config-defaults.php
        // * or any user having USER_RIGHT_SUPERADMIN right

        // Let's check if I am the Initial SuperAdmin

        $user = User::model()->findByAttributes(array('parent_id' => 0));

        if (!is_null($user) && $user->uid == $loginID)
            $initialSuperadmin=true;
        else
            $initialSuperadmin=false;

        if ($initialSuperadmin === true)
        {
            Yii::app()->session['USER_RIGHT_INITIALSUPERADMIN'] = 1;
        }
        else
            Yii::app()->session['USER_RIGHT_INITIALSUPERADMIN'] = 0;
    }

    /**
    * Prints Admin Header
    *
    * @access protected
    * @param bool $meta
    * @param bool $return
    * @return mixed
    */
    public function _getAdminHeader($meta = false, $return = false)
    {
        if (empty(Yii::app()->session['adminlang']))
            Yii::app()->session["adminlang"] = Yii::app()->getConfig("defaultlang");

        $data = array();
        $data['adminlang'] = Yii::app()->session['adminlang'];

        //$data['admin'] = getLanguageRTL;
        $data['test'] = "t";
        $data['languageRTL']="";
        $data['styleRTL']="";

        Yii::app()->loadHelper("surveytranslator");

        if (getLanguageRTL(Yii::app()->session["adminlang"]))
        {
            $data['languageRTL'] = " dir=\"rtl\" ";
            $data['bIsRTL']=true;
        }
        else
        {
            $data['bIsRTL']=false;
        }

        $data['meta']="";
        if ($meta)
        {
            $data['meta']=$meta;
        }

        $data['baseurl'] = Yii::app()->baseUrl . '/';
        $data['datepickerlang']="";
        if (Yii::app()->session["adminlang"] != 'en')
            Yii::app()->getClientScript()->registerScriptFile(App()->baseUrl . "/third_party/jqueryui/development-bundle/ui/i18n/jquery.ui.datepicker-" . Yii::app()->session['adminlang'] .".js");
            
            
        $data['sitename'] = Yii::app()->getConfig("sitename");
        $data['admintheme'] = Yii::app()->getConfig("admintheme");
        $data['firebug'] = useFirebug();

        if (!empty(Yii::app()->session['dateformat']))
            $data['formatdata'] = getDateFormatData(Yii::app()->session['dateformat']);

        $out = $this->renderPartial("/admin/super/header", $data, true);
        
        
        if ($return)
        {
            return $out;
        }
        else
        {
            echo $out;
        }
    }

    /**
    * Prints Admin Footer
    *
    * @access protected
    * @param string $url
    * @param string $explanation
    * @param bool $return
    * @return mixed
    */
    public function _getAdminFooter($url, $explanation, $return = false)
    {
        $clang = $this->lang;
        $data['clang'] = $clang;

        $data['versionnumber'] = Yii::app()->getConfig("versionnumber");

        $data['buildtext'] = "";
        if(Yii::app()->getConfig("buildnumber")!="") {
            $data['buildtext']= "Build ".Yii::app()->getConfig("buildnumber");
        }

        //If user is not logged in, don't print the version number information in the footer.
        if (empty(Yii::app()->session['loginID']))
        {
            $data['versionnumber']="";
            $data['versiontitle']="";
            $data['buildtext']="";
        }
        else
        {
            $data['versiontitle'] = $clang->gT('Version');
        }

        $data['imageurl'] = Yii::app()->getConfig("imageurl");
        $data['url'] = $url;

        return $this->renderPartial("/admin/super/footer", $data, $return);

    }

    /**
    * Shows a message box
    *
    * @access public
    * @param string $title
    * @param string $message
    * @param string $class
    * @return void
    */
    public function _showMessageBox($title,$message,$class="header ui-widget-header")
    {
        $data['title'] = $title;
        $data['message'] = $message;
        $data['class'] = $class;
        $data['clang'] = $this->lang;

        $this->renderPartial('/admin/super/messagebox', $data);
    }

    /**
    * _showadminmenu() function returns html text for the administration button bar
    *
    * @access public
    * @global string $homedir
    * @global string $scriptname
    * @global string $surveyid
    * @global string $setfont
    * @global string $imageurl
    * @param int $surveyid
    * @return string $adminmenu
    */
    public function _showadminmenu($surveyid = false)
    {

        $clang = $this->lang;
        $data['clang']= $clang;

        if (Yii::app()->session['pw_notify'] && Yii::app()->getConfig("debug")<2)  {
            Yii::app()->session['flashmessage'] = $clang->gT("Warning: You are still using the default password ('password'). Please change your password and re-login again.");
        }

        $data['showupdate'] = (Yii::app()->session['USER_RIGHT_SUPERADMIN'] == 1 && getGlobalSetting("updatenotification")!='never' && getGlobalSetting("updateavailable")==1 && Yii::app()->getConfig("updatable") );
        if($data['showupdate'])
        {
        $data['aUpdateVersions'] = json_decode(getGlobalSetting("updateversions"),true);
            $aUpdateTexts=array();
            foreach ($data['aUpdateVersions'] as $aVersion)
            {
               $aUpdateTexts[]=$aVersion['versionnumber'].'('.$aVersion['build'].')';
            }
            $data['sUpdateText']=implode(' '.$clang->gT('or').' ',$aUpdateTexts);
        }
        $data['surveyid'] = $surveyid;
        $data['iconsize'] = Yii::app()->getConfig('adminthemeiconsize');
        $data['sImageURL'] = Yii::app()->getConfig('adminimageurl');
        $this->renderPartial("/admin/super/adminmenu", $data);

    }

    public function _loadEndScripts()
    {
        static $out = false;
        if ($out)
            return true;
        $out = true;
        if (empty(Yii::app()->session['metaHeader']))
            Yii::app()->session['metaHeader'] = '';

        unset(Yii::app()->session['metaHeader']);

        return $this->renderPartial('/admin/endScripts_view', array());
    }
}
