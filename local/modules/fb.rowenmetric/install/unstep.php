<?php
global $APPLICATION;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);
if (!check_bitrix_sessid()) {
    return;
}
if ($errorException = $APPLICATION->getException()) {
    CAdminMessage::showMessage(Loc::getMessage('MODULE_UNINSTALL_FAILED') . ': ' . $errorException->GetString()
    );
} else {
    CAdminMessage::showNote(Loc::getMessage('MODULE_UNINSTALL_SUCCESS'));
}
?>
<!-- Кнопка возврата к списку модулей -->
<form action="<?= $APPLICATION->getCurPage(); ?>">
    <input type="hidden" name="lang" value="<?=LANGUAGE_ID;?>"/>
    <input type="submit" value="<?=Loc::getMessage('MODULE_RETURN_MODULES')?>">
</form>
