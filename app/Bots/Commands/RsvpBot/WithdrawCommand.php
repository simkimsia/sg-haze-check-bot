<?php

namespace App\Bots\Commands\RsvpBot;

use Log;
use Telegram\Bot\Actions;
use Telegram\Bot\Commands\Command;
use App\Event;
use App\Attendee;

class WithdrawCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "withdraw";

    /**
     * @var string Command Description
     */
    protected $description = "Remove yourself from the event!";

    /**
     * @inheritdoc
     */
    public function handle($arguments)
    {
        // This will send a message using `sendMessage` method behind the scenes to
        // the user/chat id who triggered this command.
        // `replyWith<Message|Photo|Audio|Video|Voice|Document|Sticker|Location|ChatAction>()` all the available methods are dynamically
        // handled when you replace `send<Method>` with `replyWith` and use the same parameters - except chat_id does NOT need to be included in the array.
        $chatId       = $this->getUpdate()->getMessage()->getChat()->getId();
        $fromUser     = $this->getUpdate()->getMessage()->getFrom();

        $event = Event::where('chat_id', $chatId)->first();

        if (!$event) {
            $this->replyWithMessage(['text' => 'You don\'t got not event to attend cuz.']);
            return false;
        }

        $attendees = $this->findAllAttendees($event);


        if ($arguments != '') {
            Attendee::where(['username' => $arguments, 'user_id' => 0])->delete();
        } else {
            if (!$this->isNotAttending($attendees, $fromUser->getId())) {
                Attendee::where('user_id', $fromUser->getId())->delete();
            }
        }

        $eventAttendees = $this->findAllAttendees($event);

        $text = $this->prepareText($event, $eventAttendees);

        // This will update the chat status to typing...
        $this->replyWithChatAction(['action' => Actions::TYPING]);

        // This will prepare a list of available commands and send the user.
        // First, Get an array of all registered commands
        // They'll be in 'command-name' => 'Command Handler Class' format.

        // Reply with the commands list
        $this->replyWithMessage(['text' => $text]);
    }

    private function findAllAttendees($event)
    {
        $attendees = Attendee::where('event_id', $event['id'])->get();

        return $attendees;
    }

    private function prepareText($event, $attendees)
    {
        $text = "Event: \n\n";
        $text .= $event['description'] . "\n\n";
        $i = 0;
        foreach ($attendees as $attendee) {
            $text .= $attendee['username'] . "\n";
            $i = $i + $attendee['counter'];
        }
        $text .= "\nNumber of attendees: " . $i . "\n";
        $text .= "Click here to attend!\n";
        $text .= "/attending";

        return $text;
    }

    private function isNotAttending($attendees, $newUserId)
    {
        $attended = $attendees->filter(function ($attendee) use ($newUserId) {
            return $attendee->user_id == $newUserId;
        })->toArray();

        return (empty($attended));
    }
}
