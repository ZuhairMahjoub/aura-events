<?php 
namespace App\Services;

use App\Models\Image;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

class MediaService
{
    public function storeTempUpload(UploadedFile $file): string
    {
        $filename = (string) Str::ulid() . '.' . $file->getClientOriginalExtension();
        return $file->storeAs('temp', $filename, 'public');
    }

    public function moveAndAttach(string $tempPath, Model $model, string $destinationDir, ?string $altText = null): Image
    {
        if (!Storage::disk('public')->exists($tempPath)) {
            throw new FileNotFoundException("Temporary file not found at: {$tempPath}");
        }

        $filename = basename($tempPath);
        $finalPath = trim($destinationDir, '/') . '/' . $filename;

        Storage::disk('public')->move($tempPath, $finalPath);

        return $model->images()->create([
            'path'     => $finalPath,
            'alt_text' => $altText ?? 'Media image',
        ]);
    }

    public function deleteImage(Image $image): bool
    {
        if (Storage::disk('public')->exists($image->path)) {
            Storage::disk('public')->delete($image->path);
        }
        
        return $image->delete();
    }
}