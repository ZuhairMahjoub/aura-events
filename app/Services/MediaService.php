<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

class MediaService
{
    // المجلد النهائي للملفات الدائمة فقط
    private const BASE_DIR = 'uploads';

    /**
     * الخطوة 1 — رفع مؤقت إلى public/temp/ مباشرة
     */
    public function storeTempUpload(UploadedFile $file): string
    {
        $filename = (string) Str::ulid() . '.' . $file->getClientOriginalExtension();
        
        // تعديل المسار ليصبح في public/temp مباشرة
        $tempDir  = public_path('temp');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $file->move($tempDir, $filename);

        // إرجاع المسار المؤقت
        return 'temp/' . $filename;
    }

    /**
     * الخطوة 2 — نقل الملف من public/temp/ إلى مجلده النهائي وربطه بالقاعدة
     */
    public function moveAndAttach(
        string  $tempPath,
        Model   $model,
        string  $destinationDir,
        ?string $altText = null
    ): Image {
        // المسار الفعلي للملف المؤقت في public/temp
        $absTemp  = public_path('temp' . DIRECTORY_SEPARATOR . basename($tempPath));
        
        // المسار النهائي في public/uploads/...
        $finalDir = public_path(self::BASE_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, trim($destinationDir, '/')));
        $filename = basename($absTemp);
        $absFinal = $finalDir . DIRECTORY_SEPARATOR . $filename;

        // المسار الذي سيُخزن في قاعدة البيانات للملف النهائي
        $storedPath = self::BASE_DIR . '/' . trim($destinationDir, '/') . '/' . $filename;

        if (! file_exists($absTemp)) {
            throw new FileNotFoundException("الملف المؤقت غير موجود في: {$absTemp}");
        }

        if (! is_dir($finalDir)) {
            mkdir($finalDir, 0755, true);
        }

        if (! rename($absTemp, $absFinal)) {
            throw new \RuntimeException("فشل نقل الملف إلى: {$absFinal}");
        }

        return $model->images()->create([
            'path'     => $storedPath,
            'alt_text' => $altText ?? 'Media image',
        ]);
    }
    /**
     * حذف صورة نهائياً من المجلد العام (public) ومن قاعدة البيانات.
     */
    public function deleteImage(Image $image): bool
    {
        if (!$image->path) {
            return $image->delete();
        }

        // بما أن المسار مخزن في القاعدة كـ "uploads/arrangements/..."
        // نقوم بتحويل السلاشات لتناسب بيئة Windows وتحديد المسار المطلق
        $absPath = public_path(str_replace('/', DIRECTORY_SEPARATOR, $image->path));

        // التحقق من وجود الملف فعلياً على القرص قبل محاولة حذفه
        if (file_exists($absPath)) {
            @unlink($absPath); // الـ @ تمنع انهيار النظام لو كان الملف قيد الاستخدام أو مقفلاً من السيرفر
        }

        // حذف السجل من قاعدة البيانات (يدعم Soft Delete لو كان مفعلاً في الموديل)
        return $image->delete();
    }
}