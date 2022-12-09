<?php

namespace DS\Core\Mail;

class Mailer
{

    protected $view;
    protected $mailer;

    public function __construct($view, $mailer)
    {
        $this->view = $view;
        $this->mailer = $mailer;
    }

    public function send($body, $data, $callback)
    {
        $message = new Message($this->mailer);
        $this->mailer->From = $this->mailer->Username;
        $message->body(str_replace(array_keys($data), array_values($data), $body));

        call_user_func($callback, $message);

        $result = $this->mailer->send();
        $this->clearAll();

        return $result;
    }

    public function clearAll()
    {
        $this->mailer->ClearAddresses();
        $this->mailer->ClearAttachments();
        $this->mailer->ClearReplyTos();
        $this->mailer->ClearAllRecipients();
        $this->mailer->ClearCustomHeaders();
    }

}
