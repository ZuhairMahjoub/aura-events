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
    // استخدام القرص العام المربوط بالـ Symlink
    private const DISK = 'public';

    // المجلد النهائي للملفات الدائمة داخل قرص الـ storage
    private const BASE_DIR = 'uploads';

    /**
     * الخطوة 1 — رفع مؤقت إلى storage/app/public/temp/ مباشرة
     */
    public function storeTempUpload(UploadedFile $file): string
    {
        $filename = (string) Str::ulid() . '.' . $file->getClientOriginalExtension();
        
        // رفع الملف مباشرة باستخدام Facade التخزين إلى مجلد temp داخل القرص العام
        $tempPath = $file->storeAs('temp', $filename, self::DISK);

        if (!$tempPath) {
            throw new \RuntimeException("فشل رفع الملف المؤقت");
        }

        // إرجاع المسار المؤقت (مثل: temp/01KVA...)
        return $tempPath;
    }

    /**
     * الخطوة 2 — نقل الملف من المجلد المؤقت في الـ storage إلى مجلده النهائي وربطه بالقاعدة
     */
    public function moveAndAttach(
        string  $tempPath,
        Model   $model,
        string  $destinationDir,
        ?string $altText = null
    ): Image {
        
        // التحقق من وجود الملف المؤقت داخل القرص
        if (!Storage::disk(self::DISK)->exists($tempPath)) {
            throw new FileNotFoundException("الملف المؤقت غير موجود في القرص: {$tempPath}");
        }

        $filename = basename($tempPath);
        
        // المسار النهائي الموحد داخل القرص الدائم
        $finalPath = self::BASE_DIR . '/' . trim($destinationDir, '/') . '/' . $filename;

        // نقل الملف بأمان عبر نظام الـ Storage التابع للارفيل
        if (!Storage::disk(self::DISK)->move($tempPath, $finalPath)) {
            throw new \RuntimeException("فشل نقل الملف إلى: {$finalPath}");
        }

        // تخزين المسار الجديد في قاعدة البيانات
        return $model->images()->create([
            'path'     => $finalPath,
            'alt_text' => $altText ?? 'Media image',
        ]);
    }

    /**
     * حذف صورة نهائياً من قرص التخزين ومن قاعدة البيانات.
     */
    public function deleteImage(Image $image): bool
    {
        if (!$image->path) {
            return $image->delete();
        }

        // التحقق من وجود الملف في القرص وحذفه فيزيائياً
        if (Storage::disk(self::DISK)->exists($image->path)) {
            Storage::disk(self::DISK)->delete($image->path);
        }

        // حذف السجل من قاعدة البيانات
        return $image->delete();
    }
}