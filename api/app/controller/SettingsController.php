<?php
require_once __DIR__ . '/../helpers.php';

class SettingsController{
    public function updatePreferences(){
        require_auth();
        try{
            verify_csrf();
            $language = (string)($_POST['language'] ?? '');
            $theme = (string)($_POST['theme_mode'] ?? '');
            $accent = (string)($_POST['accent_color'] ?? '');
            $density = (string)($_POST['ui_density'] ?? '');

            if(!in_array($language, supported_languages(), true)
                || !in_array($theme, supported_theme_modes(), true)
                || !in_array($accent, supported_accent_colors(), true)
                || !in_array($density, supported_ui_densities(), true)){
                throw new Exception(t('invalid_action'));
            }

            $me = (int)$_SESSION['unique_id'];
            $stmt = db()->prepare("UPDATE users SET language=?, theme_mode=?, accent_color=?, ui_density=?, updated_at=NOW() WHERE unique_id=?");
            $stmt->bind_param('ssssi', $language, $theme, $accent, $density, $me);
            $stmt->execute();
            $_SESSION['flash_success'] = t('preferences_updated');
        }catch(Throwable $e){
            $_SESSION['flash_error'] = $e->getMessage();
        }
        header('Location: ../settings.php#appearance');
        exit;
    }
}
