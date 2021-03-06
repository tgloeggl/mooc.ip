<?php
/**
* preliminary_participants.php
*
* @author Till Glöggler <tgloeggl@uos.de>
* @access public
*/
require_once 'lib/classes/CronJob.class.php';

class PreliminarParticipants extends CronJob
{

    public static function getName()
    {
        return dgettext('mooc', 'MOOC.IP - Vorläufige Nutzer in Veranstaltung übertragen');
    }

    public static function getDescription()
    {
        return dgettext('mooc', 'Ändert nach Kursstart den Status aller vorläufig Teilnehmenden zu regulär Teilnehmenden und ändert das Anledeverfahren auf Direkteintrag.');
    }

    public function execute($last_result, $parameters = array())
    {
        $db = DBManager::get();
        $status = Config::getInstance()->MOOC_SEM_CLASS_CONFIG_ID;
        // get all courses with preliminary access
        $res = $db->query("SELECT seminar_id FROM seminare WHERE admission_prelim = 1 AND status = ".  $status);

        while ($seminar_id = $res->fetchColumn()) {
            $course = Course::find($seminar_id);
            $start_date = 0;

            // check start-time
            foreach ($course->datafields as $entry) {
                #print_r($entry->datafield->name);
                #echo "\n\n";

                if ($entry->datafield->name == '(M)OOC Startdatum') {
                    $start_date = strtotime($entry->content);
                }
            }

            echo 'Kurs "'. $course->name .'" ('. $seminar_id .') beginnt am '. date('d.m.Y, H:i', $start_date) ."\n";

            // if the course has started, change it from preliminary to direct
            // and make all preliminaries real participants
            if ($start_date <= time()) {
                echo 'Kurs hat begonnen...' . "\n";

                // change the admission_prelim status only, if the course has started
                $course->admission_prelim = 0;
                $course->store();

                // move participants
                $stmt = $db->prepare("INSERT INTO seminar_user
                            (user_id, seminar_id, status, mkdate)
                        SELECT user_id, seminar_id, 'autor', mkdate
                            FROM admission_seminar_user
                            WHERE seminar_id = ?");
                $stmt->execute(array($seminar_id));

                $stmt = $db->prepare("DELETE FROM admission_seminar_user
                        WHERE seminar_id = ?");
                $stmt->execute(array($seminar_id));

                echo '... alle Teilnehmenden wurden übertragen.' . "\n";
            }

            // change preliminary access to direct access for course
            unset($course);
        }

        return true;
    }
}
