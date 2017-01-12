<?php

/**
 * @property \Course $course
 * @property string  $cid
 * @property array   $fields
 * @property array   $userInput
 */
class RegistrationsController extends MoocipController {

    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);
        $this->cid = Request::option('moocid');
    }

    public function terms_action()
    {
        # just render template
    }

    public function privacy_policy_action()
    {
        $this->privacyPolicy = Config::get()->getValue(\Mooc\PRIVACY_POLICY_ID);
    }

    public function new_action()
    {
        if (Navigation::hasItem('/mooc/registrations')) {
            Navigation::activateItem("/mooc/registrations");
        }

        $this->course = \Course::find($this->cid);
        $this->fields = $this->parseRegistrationFormFields();
        $this->userInput = array();
    }

    public function create_action()
    {
        $this->course = Course::find($this->cid);
        $this->fields = $this->parseRegistrationFormFields();

        if (!Request::option('accept_tos')) {
            $this->flash['error'] = _mooc('Sie m�ssen die Nutzungsbedingungen akzeptieren!');
            return;
        }

        switch (Request::get('type')) {
            default:
            case 'register':
                $this->register();
                break;
            case 'login':
                $this->loginAndRegister();
                break;
            case 'create':
                $this->createAccountAndRegister();
                break;
        }
    }

    function show_action($user_id)
    {
        if (Request::get('username')) {
            $this->loginUser();
            $this->redirect('courses/show/' . $this->cid . '?cid=' . $this->cid);
        } else {
            if (Navigation::hasItem('/mooc/registrations')) {
                Navigation::activateItem("/mooc/registrations");
            }

            PageLayout::addScript($this->plugin->getPluginURL().'/assets/js/registrations.js');

            $this->course = \Course::find($this->cid);
            $this->user   = User::find($user_id);
        }
    }

    function resend_mail_action($user_id)
    {
        $course = \Course::find($this->cid);
        $user   = User::find($user_id);

        if ($_SESSION['mooc']['register']['username'] == $user->username) {
            $this->sendMail($course, $user->username, $_SESSION['mooc']['register']['password']);
            $this->render_json(array('message' => _mooc('Die Best�tigungsmail wurde erfolgreich erneut versendet!')));
        } else {
            throw new Trails_Exception(400, 'Invalid session');
        }
    }

    /*******************/
    /* PRIVATE METHODS */
    /*******************/


    private function register()
    {
        global $user;

        if ($user->id === 'nobody') {
            return $this->error('Nicht angemeldet!', 'registrations/new');
        }

        $course = $this->registerUserWithCourse($user, $this->cid);
        $this->redirectToCourse($course);
    }

    private function loginAndRegister()
    {
        $user = $this->loginUser();
        if (!$user) {
            return $this->error('Fehler beim Anmelden', 'registrations/new');
        }

        $course = $this->registerUserWithCourse($user, $this->cid);
        $this->redirectToCourse($course);
    }

    // hide implementation details when redirecting to a course
    private function redirectToCourse($course)
    {
        $cid = $course->id;

        if ($course->admission_prelim) {
            $this->redirect('courses/show/' . $cid . '?moocid=' . $cid);
        } else {
            $this->redirect('courses/show/' . $cid . '?cid=' . $cid);
        }
    }

    private function createAccountAndRegister()
    {
        $this->userInput = array();
        $filledRequiredFields = true;

        foreach ($this->fields as $field) {
            // string "fields" are free text that is displayed as is and
            // must not be validated
            if (!is_array($field)) {
                continue;
            }

            $fieldName = $field['fieldName'];
            $fieldValue = Request::get($fieldName);
            $this->userInput[$fieldName] = $fieldValue;

            if ($field['required'] && (trim($fieldValue) === '')) {
                $filledRequiredFields = false;
            }
        }

        if (!$filledRequiredFields) {
            $this->flash['error'] = _mooc('Sie m�ssen alle Pflichtfelder ausf�llen!');

            return;
        }

        try {
            $user = $this->createAccount($this->userInput);
        } catch (Exception $e) {
            return $this->error('Fehler beim Anlegen des Accounts: ' . htmlReady($e->getMessage()), 'registrations/new');
        }

        $this->registerUserWithCourse($user, $this->cid);

        $this->redirect('registrations/show/'. $user->getId() .'?moocid=' . $this->cid);
    }

    private function error($msg, $url)
    {
        $this->flash['error'] = $msg;
        $this->redirect($url . '?moocid=' . $this->cid);
    }

    private function createAccount($additionalData)
    {
        // TODO: check if mail adress is valid, use Stud.IP-API if possible
        $mail = Request::get('mail');
        if (\User::findByUsername($mail)) {
            throw new Exception(_mooc('Es gibt bereits einen Nutzer mit dieser E-Mail-Adresse!'));
        }

        // add user to database
        $password = str_replace('0', 'o', substr(\base_convert(\uniqid('pass', true), 10, 36), 1, 8));

        $data = array(
            'Email'       => $mail,

            'username'    => $mail,
            'Password'    => UserManagement::getPwdHasher()->HashPassword($password),

            'Vorname'     => Request::get('vorname'),
            'Nachname'    => Request::get('nachname'),

            'perms'       => 'autor',
            'auth_plugin' => 'standard'
        );

        $user = User::create($data);

        $userinfo = new Userinfo($user->getId());
        $userinfo->store();

        // add user to special user-domain
        $user_domain = new UserDomain(\Mooc\USER_DOMAIN_NAME);
        $user_domain->addUser($user->getId());

        // send registration-mail
        $this->sendMail($this->course, $mail, $password);

        $_SESSION['mooc']['register'] = array(
            'username' => $mail,
            'password' => $password
        );

        foreach ($additionalData as $fieldName => $value) {
            if (!$this->isDataFieldFormField($fieldName)) {
                continue;
            }

            $dataField = new DataFieldStructure(array('datafield_id' => $fieldName));
            $dataField->load();

            if ($dataField->data !== false) {
                $entry = new DataFieldTextlineEntry($dataField, $user->getId(), $value);
                $entry->store();
            }
        }

        return $user;
    }

    private function sendMail($course, $mail, $password)
    {
        URLHelper::setBaseUrl($GLOBALS['ABSOLUTE_URI_STUDIP']);
        $link = $this->url_for('courses/show/' . $course->id);

        // send mail with password to user
        $mail_msg = sprintf(
            _mooc("Ihre Zugangsdaten f�r den MOOC-Kurs '%s':\n\n"
            . "Benutzername: %s \n"
            . "Passwort: %s \n\n"
            . "Hier kommen Sie direkt zum Kurs:\n %s"),
            $course->name, $mail, $password, $link
        );
        StudipMail::sendMessage($mail, sprintf(_mooc('Zugang zum MOOC-Kurs "%s"'), $course->name), $mail_msg);
    }

    private function loginUser()
    {
        $username = Request::get("username");
        $password = Request::get("password");

        if (isset($username) && isset($password)) {
            $result = StudipAuthAbstract::CheckAuthentication($username, $password);
        }

        if (!isset($result) || $result['uid'] === false) {
            return false;
        }

        $user = User::findByUsername($username);

        if (!$user) {
            return false;
        }

        $this->startSession($user);

        return $user;
    }

    private function startSession($user)
    {
        $GLOBALS['auth'] = new Seminar_Auth();
        $GLOBALS['auth']->auth = array(
            'uid'   => $user->user_id,
            'uname' => $user->username,
            'perm'  => $user->perms,
            "auth_plugin" => $user->auth_plugin,
        );

        $GLOBALS['user'] = new Seminar_User($user);

        $GLOBALS['perm'] = new Seminar_Perm();
        $GLOBALS['MAIL_VALIDATE_BOX'] = false;
    }

    private function registerUserWithCourse($user, $cid)
    {
        $course = new Course($cid);

        if ($course->admission_prelim) {
            $new = new AdmissionApplication(array($user->id,  $cid));

            if ($new->isNew()) {
                $new->status = 'accepted';
                $new->store();
            }
        } else {
            $new = new CourseMember(array($cid, $user->id));

            if ($new->isNew()) {
                $new->status = 'autor';
                $new->label = '';
                $new->store();
            }
        }

        return $course;
    }

    /**
     * @return array
     */
    private function parseRegistrationFormFields()
    {
        $fields = explode("\n", Config::get()->getValue(\Mooc\REGISTRATION_FORM_CONFIG_ID));
        $parsedFields = array();
        $fieldNameMap = array(
            'firstname' => 'vorname',
            'lastname' => 'nachname',
            'email' => 'mail',
            'birthday' => 'geburtsdatum',
            'sex' => 'geschlecht',
            'terms_of_service' => 'accept_tos',
        );

        foreach ($fields as $field) {
            if (substr($field, 0, 6) === 'field:') {
                $field = trim($field);
                $separatorPos = strpos($field, '|');
                $required = false;
                $label = null;
                $choices = null;

                // field name and label are separated by a pipe character
                if ($separatorPos !== false) {
                    $label = substr($field, $separatorPos + 1);
                    $fieldName = substr($field, 6, $separatorPos - 6);
                } else {
                    $fieldName = substr($field, 6);
                }

                // the field is required if its name ends with an asterisk character
                if (substr($fieldName, -1) === '*') {
                    $fieldName = substr($fieldName, 0, -1);
                    $required = true;
                }

                // map configured field names to user properties
                if (isset($fieldNameMap[$fieldName])) {
                    $fieldName = $fieldNameMap[$fieldName];
                    $fieldType = 'text';
                } elseif ($this->isDataFieldFormField($fieldName)) {
                    $dataField = new \DataField($fieldName);
                    $fieldType = $dataField->type;

                    if ($dataField->type === 'selectbox') {
                        $choices = explode("\n", $dataField->typeparam);
                    }
                } elseif ($fieldName !== 'terms_of_service') {
                    // skip the field if it is not recognised
                    continue;
                }

                if ($fieldName === 'geschlecht') {
                    $choices = array(
                        _mooc('unbekannt'),
                        _mooc('m�nnlich'),
                        _mooc('weiblich'),
                    );
                }

                $parsedFields[] = array(
                    'fieldName' => $fieldName,
                    'label' => $label,
                    'required' => $required,
                    'choices' => $choices,
                    'type' => $fieldType,
                );
            } else {
                $parsedFields[] = $field;
            }
        }

        return $parsedFields;
    }

    private function isDataFieldFormField($fieldName)
    {
        return preg_match('/^[a-z0-9]{32}$/i', $fieldName);
    }
}
