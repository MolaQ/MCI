<?php

namespace App\Enums;

enum SchoolOfferKind: string
{
    case Technikum = 'technikum';
    case Branzowa = 'branzowa';
    case Liceum = 'liceum';
    case Policealna = 'policealna';
    case Kurs = 'kurs';
    case Inne = 'inne';

    public function label(): string
    {
        return __("enums.offer_kind.{$this->value}");
    }
}
