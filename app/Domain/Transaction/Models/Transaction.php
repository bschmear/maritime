<?php

namespace App\Domain\Transaction\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domain\Document\Models\Document;
use App\Models\Concerns\HasDocuments;

class Transaction extends Model
{
    use HasDocuments;


}
