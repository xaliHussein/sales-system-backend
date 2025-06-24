<?php

namespace App\Traits;

use File;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

trait UploadImage
{
    public function upload_multi_Pictures($images, $path)
    {

         // Check if the directory exists, if not create it
         if (!file_exists(public_path() . $path)) {
            File::makeDirectory(public_path() . $path, 0755, true);
        }

        $names = [];
        foreach ($images as $image) {

            $image = explode(',', $image)[1];
            $imgdata = base64_decode($image);
            $f = finfo_open();
            $mime_type = finfo_buffer($f, $imgdata, FILEINFO_MIME_TYPE);
            $type = explode('/', $mime_type)[1];
            $filename = time() . Str::random(2) . '.' . $type;
            File::put(public_path() . $path . $filename, $imgdata);
            array_push($names, $path . $filename);
        }
    }

    public function uploadPdf($pdf, $path)
    {

        $pdf = explode(',', $pdf)[1];
        $imgdata = base64_decode($pdf);
        $f = finfo_open();
        $mime_type = finfo_buffer($f, $imgdata, FILEINFO_MIME_TYPE);
        $type = explode('/', $mime_type)[1];
        $filename = time() . Str::random(2) . '.' . $type;
        File::put(public_path() . $path . $filename, $imgdata);

        return $path . $filename;
    }


    public function uploadPicture($picture, $path)
    {

        // Check if the directory exists, if not create it
        if (!file_exists(public_path() . $path)) {
            File::makeDirectory(public_path() . $path, 0755, true);
        }

        // Extract base64 data
        $picture = explode(',', $picture)[1];
        $imgdata = base64_decode($picture);
        $f = finfo_open();
        $mime_type = finfo_buffer($f, $imgdata, FILEINFO_MIME_TYPE);
        $type = explode('/', $mime_type)[1];

        // Create an image resource from the base64 data
        $image = imagecreatefromstring($imgdata);


        // Get the current dimensions of the image
        $width = imagesx($image);
        $height = imagesy($image);

        // Define new dimensions
        $maxWidth = 1000; // You can adjust this value
        $maxHeight = 800; // You can adjust this value
        $aspectRatio = $width / $height;

        if ($width > $height) {
            $newWidth = min($maxWidth, $width);
            $newHeight = $newWidth / $aspectRatio;
        } else {
            $newHeight = min($maxHeight, $height);
            $newWidth = $newHeight * $aspectRatio;
        }

        // Create a new true color image with the new dimensions
        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // Resize the original image and copy it to the new image resource
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Generate a filename and save the image as JPEG with compression

        $filename = time() . Str::random(2) . '.' . $type;
        $filePath = public_path() . $path . $filename;

        // Save the image with a higher quality setting
        imagejpeg($newImage, $filePath, 100); // Adjust quality as needed

        // Free up memory
        imagedestroy($image);
        imagedestroy($newImage);

        return $path . $filename;
    }
}
