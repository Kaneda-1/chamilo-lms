<?php

declare(strict_types=1);

namespace Chamilo\CoreBundle\Migrations\Schema\V200;

use Chamilo\CoreBundle\Migrations\AbstractMigrationChamilo;
use Doctrine\DBAL\Schema\Schema;
use SubLanguageManager;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use const FILE_APPEND;

final class Version20240122221400 extends AbstractMigrationChamilo
{
    public function getDescription(): string
    {
        return 'Migration of sublanguages and Vue translation updates.';
    }

    public function up(Schema $schema): void
    {
        // Fetching sublanguages from the database.
        $sql = "SELECT * FROM language WHERE parent_id IS NOT NULL AND isocode NOT IN('".implode("', '", Version20::ALLOWED_SUBLANGUAGES)."')";
        $sublanguages = $this->connection->executeQuery($sql)->fetchAllAssociative();

        foreach ($sublanguages as $sublanguage) {
            $newIsoCode = $this->updateAndGenerateSubLanguage($sublanguage);
            $this->generatePoFileFromTrad4All($sublanguage['english_name'], $newIsoCode);
        }

        // Update Vue translations after processing all sublanguages.
        $this->executeVueTranslationsUpdate();

        // Delete the 'import' folder at the end of the process.
        // $this->deleteImportFolder();
    }

    private function updateAndGenerateSubLanguage(array $sublanguage): string
    {
        // Get the parent language ID
        $parentId = $sublanguage['parent_id'];

        // Query to obtain the isocode of the parent language
        $parentIsoQuery = 'SELECT isocode FROM language WHERE id = ?';
        $parentIsoCode = $this->connection->executeQuery($parentIsoQuery, [$parentId])->fetchOne();

        // Get the prefix of the parent language's isocode
        $firstIso = explode('_', $parentIsoCode)[0];
        $newIsoCode = $firstIso.'_'.$sublanguage['id'];

        // Update the isocode in the language table
        $updateLanguageQuery = 'UPDATE language SET isocode = ? WHERE id = ?';
        $this->connection->executeStatement($updateLanguageQuery, [$newIsoCode, $sublanguage['id']]);
        error_log('Updated language table for id '.$sublanguage['id']);

        // Check and update in settings
        $updateSettingsQuery = "UPDATE settings SET selected_value = ? WHERE variable = 'platform_language' AND selected_value = ?";
        $this->connection->executeStatement($updateSettingsQuery, [$newIsoCode, $sublanguage['english_name']]);
        error_log('Updated settings for language '.$sublanguage['english_name']);

        // Check and update in user table
        $updateUserQuery = 'UPDATE user SET locale = ? WHERE locale = ?';
        $this->connection->executeStatement($updateUserQuery, [$newIsoCode, $sublanguage['english_name']]);
        error_log('Updated user table for language '.$sublanguage['english_name']);

        // Check and update in course table
        $updateCourseQuery = 'UPDATE course SET course_language = ? WHERE course_language = ?';
        $this->connection->executeStatement($updateCourseQuery, [$newIsoCode, $sublanguage['english_name']]);
        error_log('Updated course table for language '.$sublanguage['english_name']);

        // Return the new ISO code.
        return $newIsoCode;
    }

    private function generatePoFileFromTrad4All(string $englishName, string $isocode): void
    {
        $kernel = $this->container->get('kernel');
        $updateRootPath = $this->getUpdateRootPath();

        $langPath = $updateRootPath.'/main/lang/'.$englishName.'/trad4all.inc.php';
        $destinationFilePath = $kernel->getProjectDir().'/var/translations/messages.'.$isocode.'.po';
        $originalFile = $updateRootPath.'/main/lang/english/trad4all.inc.php';

        if (!file_exists($langPath)) {
            error_log("Original file not found: $langPath");

            return;
        }

        $terms = SubLanguageManager::get_all_language_variable_in_file(
            $originalFile,
            true
        );

        foreach ($terms as $index => $translation) {
            $terms[$index] = trim(rtrim($translation, ';'), '"');
        }

        $header = 'msgid ""'."\n".'msgstr ""'."\n".
            '"Project-Id-Version: chamilo\n"'."\n".
            '"Language: '.$isocode.'\n"'."\n".
            '"Content-Type: text/plain; charset=UTF-8\n"'."\n".
            '"Content-Transfer-Encoding: 8bit\n"'."\n\n";
        file_put_contents($destinationFilePath, $header);

        $originalTermsInLanguage = SubLanguageManager::get_all_language_variable_in_file(
            $langPath,
            true
        );

        $termsInLanguage = [];
        foreach ($originalTermsInLanguage as $id => $content) {
            if (!isset($termsInLanguage[$id])) {
                $termsInLanguage[$id] = trim(rtrim($content, ';'), '"');
            }
        }

        $bigString = '';
        $doneTranslations = [];
        foreach ($terms as $term => $englishTranslation) {
            if (isset($doneTranslations[$englishTranslation])) {
                continue;
            }
            $doneTranslations[$englishTranslation] = true;
            $translatedTerm = $termsInLanguage[$term] ?? '';

            // Here we apply a little correction to avoid unterminated strings
            // when a string ends with a \"
            if (preg_match('/\\\$/', $englishTranslation)) {
                $englishTranslation .= '"';
            }

            $search = ['\{', '\}', '\(', '\)', '\;'];
            $replace = ['\\\{', '\\\}', '\\\(', '\\\)', '\\\;'];
            $englishTranslation = str_replace($search, $replace, $englishTranslation);
            if (preg_match('/\\\$/', $translatedTerm)) {
                $translatedTerm .= '"';
            }
            $translatedTerm = str_replace($search, $replace, $translatedTerm);
            if (empty($translatedTerm)) {
                continue;
            }
            // Now build the line
            $bigString .= 'msgid "'.$englishTranslation.'"'."\n".'msgstr "'.$translatedTerm.'"'."\n\n";
        }
        file_put_contents($destinationFilePath, $bigString, FILE_APPEND);

        error_log("Done generating gettext file in $destinationFilePath !\n");
    }

    private function executeVueTranslationsUpdate(): void
    {
        $application = new Application($this->container->get('kernel'));
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'chamilo:update_vue_translations',
        ]);
        $output = new BufferedOutput();

        $application->run($input, $output);

        $content = $output->fetch();

        error_log($content);
    }

    private function recursiveRemoveDirectory($directory): void
    {
        foreach (glob("{$directory}/*") as $file) {
            if (is_dir($file)) {
                $this->recursiveRemoveDirectory($file);
            } else {
                unlink($file);
            }
        }
        rmdir($directory);
    }

    public function down(Schema $schema): void {}
}
