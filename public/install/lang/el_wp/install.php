<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Automatically generated strings for Moodle installer
 *
 * Do not edit this file manually! It contains just a subset of strings
 * needed during the very first steps of installation. This file was
 * generated automatically by export-installer.php (which is part of AMOS
 * {@link https://moodledev.io/general/projects/api/amos}) using the
 * list of strings defined in public/install/stringnames.txt file.
 *
 * @package   installer
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['admindirname'] = 'Φάκελος διαχειριστή';
$string['availablelangs'] = 'Λίστα διαθέσιμων πακέτων γλωσσών';
$string['chooselanguagehead'] = 'Επιλογή γλώσσας';
$string['chooselanguagesub'] = 'Παρακαλούμε, επιλέξτε γλώσσα για την εγκατάσταση. Αυτή η γλώσσα θα χρησιμοποιηθεί επίσης ως προεπιλεγμένη γλώσσα για τον ιστότοπο, αν και μπορεί να αλλάξει αργότερα.';
$string['clialreadyconfigured'] = 'Το αρχείο ρυθμίσεων config.php υπάρχει ήδη. Χρησιμοποιήστε το admin/cli/install_database.php για να εγκαταστήσετε το Moodle για αυτόν τον ιστότοπο.';
$string['clialreadyinstalled'] = 'Το αρχείο ρυθμίσεων config.php υπάρχει ήδη. Χρησιμοποιήστε το admin/cli/install_database.php για να αναβαθμίσετε το Moodle για αυτόν τον ιστότοπο.';
$string['cliinstallheader'] = 'Moodle {$a} πρόγραμμα εγκατάστασης γραμμής εντολών';
$string['clitablesexist'] = 'Οι πίνακες βάσεων δεδομένων υπάρχουν ήδη. Η εγκατάσταση από γραμμή εντολών δεν μπορεί να συνεχιστεί.';
$string['databasehost'] = 'Κεντρικός Υπολογιστής Βάσης Δεδομένων';
$string['databasename'] = 'Όνομα Βάσης Δεδομένων';
$string['databasetypehead'] = 'Επιλογή οδηγού βάσης δεδομένων';
$string['dataroot'] = 'Φάκελος δεδομένων';
$string['datarootpermission'] = 'Άδεια φακέλων/καταλόγων δεδομένων';
$string['dbprefix'] = 'Πρόθεμα πινάκων';
$string['dirroot'] = 'Φάκελος Moodle';
$string['environmenthead'] = 'Έλεγχος περιβάλλοντος...';
$string['environmentsub2'] = 'Κάθε έκδοση Moodle έχει κάποια ελάχιστη απαίτηση σχετικά με την έκδοση της PHP και ενός αριθμού από αναγκαίες επεκτάσεις PHP.
Ο πλήρης έλεγχος του περιβάλλοντος πραγματοποιείται πριν κάθε εγκατάσταση και αναβάθμιση. Παρακαλούμε επικοινωνήστε με τον διαχειριστή του εξυπηρετητή εάν δεν ξέρετε πως να εγκαταστήσετε νέα έκδοση της PHP ή να ενεργοποιήσετε επεκτάσεις της.';
$string['errorsinenvironment'] = 'Ο έλεγχος του περιβάλλοντος απέτυχε!';
$string['installation'] = 'Εγκατάσταση';
$string['langdownloaderror'] = 'Δυστυχώς η γλώσσα «{$a}» δεν είναι εγκατεστημένη. Η εγκατάσταση θα συνεχιστεί στα αγγλικά.';
$string['paths'] = 'Διαδρομές';
$string['pathserrcreatedataroot'] = 'Ο Φάκελος δεδομένων ({$a->dataroot}) δεν μπορεί να δημιουργθεί από το πρόγραμμα εγκατάστασης.';
$string['pathshead'] = 'Επιβεβαίωση Διαδρομών';
$string['pathsrodataroot'] = 'Ο Φάκελος Δεδομένων δεν είναι εγγράψιμος.';
$string['pathsroparentdataroot'] = 'Ο γονικός φάκελος ({$a->parent}) δεν είναι εγγράψιμος. Ο φάκελος δεδομένων ({$a->dataroot}) δεν μπορεί να δημιουργθεί από το πρόγραμμα εγκατάστασης.';
$string['pathssubadmindir'] = 'Κάποιοι λίγοι κεντρικοί υπολογιστές ιστού χρησιμοποιούν το /admin ως ειδική διεύθυνση URL για την πρόσβαση σε κάποιο πίνακα ελέγχου ή κάτι τέτοιο. Δυστυχώς αυτό έρχεται σε αντίθεση με την τυπική τοποθεσία των σελίδων διαχείρισης (admin) του Moodle. Αυτό μπορεί να διορθωθεί με την μετονομασία του admin φακέλου στην εγκατάστασή σας, και βάζοντας αυτό το καινούργιο όνομα εδώ. Για παράδειγμα: <em>moodleadmin</em>.  Αυτό θα διορθώσει όλους τους συνδέσμους με το admin στην διεύθυνσή τους σε όλη την εγκατάσταση του Moodle σας.';
$string['pathssubdataroot'] = '<p>Ένας φάκελος όπου το Moodle θα αποθηκεύει όλα τα ανεβασμένα από τους χρήστες αρχεία.</p> <p>Αυτος ο φάκελος θα πρέπει να είναι αναγνώσιμος ΚΑΙ ΕΓΓΡΑΨΙΜΟΣ από τον χρήστη του εξυπηρετητή ιστού (συνήθως «nobody» ή «apache»).</p> <p>Δεν πρέπει να είναι προσβάσιμος κατευθείαν από τον ιστό.</p> <p>Αν ο φάκελος δεν υπάρχει, η διαδικασία εγκατάστασης θα προσπαθήσει να τον δημιουργήσει.</p>';
$string['pathssubdirroot'] = '<p>Η πλήρης διαδρομή του φακέλου που περιέχει τα αρχεία κώδικα του Moodle.</p>';
$string['pathssubwwwroot'] = '<p>Η πλήρης διεύθυνση (ιστού) από την οποία θα γίνεται η πρόσβαση στο Moodle, δηλαδή η διεύθυνση που οι χρήστες θα εισάγουν στην γραμμή διεύθυνσης του περιηγητή, για να έχουν πρόσβαση στου Moodle.</p>
<p>Δεν είναι δυνατόν να έχετε πρόβαση στο Moodle χρησιμοποιώντας πολλαπλές διευθύνσεις. Εάν ο ιστότοπος θα είναι προσβάσιμος μέσω πολλαπλών διευθύνσεων τότε επιλέξτε την ευκολότερη και εγκαταστήστε μια μόνιμη ανακατεύθυνση για καθεμία από τις άλλες διευθύνσεις.</p>
<p>Εάν ο ιστότοπός σας είναι προσβάσιμος τόσο από το Διαδίκτυο όσο και από ένα εσωτερικό δίκτυο (που συχνά λέγεται intranet) τότε χρησιμοποιήστε εδώ την δημόσια διεύθυνση.</p>
<p>Αν η τρέχουσα διεύθυνση δεν είναι σωστή, παρακαλούμε αλλάξτε την URL διεύθυνση στην γραμμή διευθύνσεων του περιηγητή σας και επανεκκινήστε την εγκατάσταση.</p>';
$string['pathsunsecuredataroot'] = 'Η τοποθεσία του Φάκελου Δεδομένων δεν είναι ασφαλής';
$string['pathswrongadmindir'] = 'Ο Φάκελος Admin δεν υπάρχει';
$string['phpextension'] = 'Επέκταση {$a} της PHP';
$string['phpversion'] = 'Έκδοση της PHP';
$string['welcomep10'] = '{$a->installername} ({$a->installerversion})';
$string['welcomep20'] = 'Βλέπετε αυτή τη σελίδα γιατί εγκαταστήσατε και ξεκινήσατε με επιτυχία το πακέτο <strong>{$a->packname} {$a->packversion}</strong> στον υπολογιστή σας. Συγχαρητήρια!';
$string['welcomep30'] = 'Αυτή η έκδοση/διανομή <strong>{$a->installername}</strong> περιλαμβάνει τις εφαρμογές για τη δημιουργία ενός περιβάλλοντος μέσα στο οποίο θα λειτουργεί το <strong>Moodle</strong>, ονομαστικά:';
$string['welcomep40'] = 'Το πακέτο περιλαμβάνει επίσης το <strong>Moodle {$a->moodlerelease} ({$a->moodleversion})</strong>.';
$string['welcomep50'] = 'Η χρήση όλων των εφαρμογών σε αυτό το πακέτο υπόκειται στις αντίστοιχες άδειες. Ολόκληρο το πακέτο <strong>{$a->installername}</strong> είναι <a href="http://www.opensource.org/docs/definition_plain.html">λογισμικό ανοικτού κώδικα</a> και διανέμεται με την <a href="http://www.gnu.org/copyleft/gpl.html">GPL</a> άδεια.';
$string['welcomep60'] = 'Οι παρακάτω σελίδες θα σας καθοδηγήσουν με εύκολα βήματα στην εγκατάσταση και ρύθμιση του <strong>Moodle</strong> στον υπολογιστή σας. Μπορείτε να δεχθείτε τις προεπιλεγμένες ρυθμίσεις ή προαιρετικά, να τις τροποποιήσετε ανάλογα με τις ανάγκες σας.';
$string['welcomep70'] = 'Πατήστε το κουμπί «Συνέχεια» για να συνεχίσετε με την εγκατάσταση του <strong>Moodle</strong>.';
$string['wwwroot'] = 'Διεύθυνση ιστού';
