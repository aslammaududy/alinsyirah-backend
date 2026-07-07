<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'nis',
        'name',
        'school_class',
        'parent_name',
        'parent_phone',
        'parent_email',
        'monthly_fee',
        'status',
        'photo_url',
    ];

    public function tuitionInvoices(): HasMany
    {
        return $this->hasMany(TuitionInvoice::class);
    }

    public function uploadPhoto(UploadedFile $file): static
    {
        return DB::transaction(function () use ($file) {
            if ($this->photo_url !== null) {
                Storage::disk('r2')->delete($this->photo_url);
            }

            $path = 'students/'.$this->id.'/'.Str::uuid().'.'.$file->guessExtension();

            Storage::disk('r2')->put($path, $file->get(), 'public');

            $this->update(['photo_url' => $path]);

            return $this;
        });
    }

    public function deletePhoto(): static
    {
        return DB::transaction(function () {
            if ($this->photo_url !== null) {
                Storage::disk('r2')->delete($this->photo_url);
                $this->update(['photo_url' => null]);
            }

            return $this;
        });
    }
}
