<?php
class FlashMessenger {
	
	public static function addMessage($str) {
		$messages = Session::getInstance()->flash_messages;
        if ($messages === null) {
            $messages = array();
        }
		$messages[] = $str;
		Session::getInstance()->flash_messages = $messages;
	}
	
	public static function getMessages() {
		$messages = Session::getInstance()->flash_messages;
		unset(Session::getInstance()->flash_messages);
        if ($messages === null) {
            return array();
        }
		return $messages;
	}

    /**
     * by calling get messages we effecively reset...
     */
    public static function reset() {
        self::getMessages();
    }
}
