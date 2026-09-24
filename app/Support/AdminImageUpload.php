<?php

namespace App\Support;

final class AdminImageUpload
{
    public const MAX_KILOBYTES = 2048;

    public const EXTENSIONS = 'jpg,jpeg,png,webp,gif,avif';

    public const MIME_TYPES = 'image/jpeg,image/png,image/webp,image/gif,image/avif';

    public static function rules(string $presence = 'required'): array
    {
        return [
            $presence,
            'file',
            'mimes:'.self::EXTENSIONS,
            'mimetypes:'.self::MIME_TYPES,
            'max:'.self::MAX_KILOBYTES,
            'dimensions:max_width=6000,max_height=6000',
        ];
    }

    public static function messages(string $attribute): array
    {
        $formatMessage = 'Chỉ hỗ trợ ảnh JPG, PNG, WEBP, GIF hoặc AVIF hợp lệ.';

        return [
            $attribute.'.uploaded' => 'Ảnh quá lớn. Vui lòng chọn ảnh nhỏ hơn 2MB',
            $attribute.'.max' => 'Ảnh quá lớn. Vui lòng chọn ảnh nhỏ hơn 2MB',
            $attribute.'.file' => $formatMessage,
            $attribute.'.mimes' => $formatMessage,
            $attribute.'.mimetypes' => $formatMessage,
            $attribute.'.dimensions' => 'Ảnh không được vượt quá 6000 × 6000 pixel.',
        ];
    }
}
