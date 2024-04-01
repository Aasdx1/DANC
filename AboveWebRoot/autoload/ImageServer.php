<?php
// Class that provides methods for working with the images.  The constructor is empty, because
// initialisation isn't needed; in fact it probably never really needs to be instanced and all
// could be done with static methods.

class ImageServer
{
    //------------------------------------------------------------------------------------------------------------------
    private $filedata;
    private $uploadResult         = null;
    private $table                = 'picdata_fff';
    private $thumbsize            = 200;          // max width / height of thumbnail images
    private $acceptedTypes        = ['image/jpeg', 'image/png', 'image/gif'];    // tiff and svg removed: image processing code can't handle them
    private $shouldOverwriteFiles = true;         // If an uploaded file has the same name as a current file, should it overwrite?

    public function __construct()
    {
    }

    //------------------------------------------------------------------------------------------------------------------
    public function upload()
    {
        global $f3;        // so that we can call functions like $f3->set() from inside here
        $f3->set('CurrentImageServer', $this); // so we can call receiveCallback
        $slug = true; // rename file to filesystem-friendly versio
        // go to the receiveCallback function to configure what happens during upload
        Web::instance()->receive('ImageServer::receiveCallback', $this->shouldOverwriteFiles, $slug);

        if ($this->uploadResult != 'success')
        {
            if($this->uploadResult == null)
            {
                $this->uploadResult = self::setUploadFailMessage("reason unknown");
            }
            echo $this->uploadResult;                // ideally this might be output from index.php
            return null;
        }

        $filedata              = $this->filedata;
        $filedata['title']     = $f3->get('POST.picname');
        $filedata['thumbnail'] = $f3->get('UPLOADS') . '/' .$this->thumbFile($filedata['name']);
        $this->createThumbnail($filedata['name'], $filedata['thumbnail'], basename($filedata['type']));
        $this->store($filedata);
        return $filedata;
    }

    //------------------------------------------------------------------------------------------------------------------

    /// What happens when trying to upload a file
    /// see https://fatfreeframework.com/3.7/web#receive
    public static function receiveCallback($file, $anything)
    {
        global $f3;
        $imageServer = $f3->get('CurrentImageServer'); // current image server object

        $imageServer->filedata = $file;        // export file data to outside this function

        if ($imageServer->filedata['size'] > (2 * 1024 * 1024)) { // if bigger than 2 MB
            $imageServer->uploadResult = ImageServer::setUploadFailMessage("File > 2MB");
            return false; // this file is not valid, return false will skip moving it
        }
        if (!in_array($imageServer->filedata['type'], $imageServer->acceptedTypes)) {        // if not an approved type
            $imageServer->uploadResult = ImageServer::setUploadFailMessage("File type not accepted");
            return false; // this file is not valid, return false will skip moving it
        }
        $imageServer->uploadResult = 'success'; // everything went fine, hurray!
        return true; // allows the file to be moved from php tmp dir to your defined upload dir
    }

    //------------------------------------------------------------------------------------------------------------------
    /// How to store file data in table
    public function store($filedata)
    {
        global $f3;            // because we need f3->get()
        $mapper            = new DB\SQL\Mapper($f3->get('DB'), $this->table);    // create DB query mapper object
        $mapper->filepath  = $filedata['name'];
        $mapper->title     = $filedata['title'];
        $mapper->type      = $filedata['type'];
        $mapper->thumbnail = $filedata['thumbnail'];
        $mapper->save();
    }

    //------------------------------------------------------------------------------------------------------------------
    // given an image ID as argument it returns data only about that image.
    public function getImageData($id)
    {
        global $f3;
        $mapper = new DB\SQL\Mapper($f3->get('DB'), $this->table);    // create DB query mapper object
        $mapper->load(['id=?',$id]);
        $imageData = [
            'filepath'   => $mapper['filepath'],
            'type'       => $mapper['type'],
            'title'      => $mapper['title'],
            'id'         => $mapper['id'],
            'thumbnail'  => $mapper['thumbnail']
        ];
        return $imageData;
    }

    //------------------------------------------------------------------------------------------------------------------
    // return data on all images
    public function getAllImageData()
    {
        global $f3;
        $mapper    = new DB\SQL\Mapper($f3->get('DB'), $this->table);    // create DB query mapper object
        $imageData = [];
        $images    = $mapper->find();

        foreach ($images as $image) {
            array_push($imageData, [
                'filepath'   => $image['filepath'],
                'type'       => $image['type'],
                'title'      => $image['title'],
                'id'         => $image['id'],
                'thumbnail'  => $image['thumbnail']
            ]);
        }
        return $imageData;
    }

    //------------------------------------------------------------------------------------------------------------------
    // Delete data record about the image, and remove its file and thumbnail file
    public function deleteService($id)
    {
        global $f3;
        $mapper = new DB\SQL\Mapper($f3->get('DB'), $this->table);    // create DB query mapper object
        $mapper->load(['id=?', $id]);                            // load DB record matching the given ID
        unlink($mapper['filepath']);                                        // remove the image file
        unlink($mapper['thumbnail']);    // remove the thumbnail file
        $mapper->erase();                                                    // delete the DB record
    }

    //------------------------------------------------------------------------------------------------------------------
    // A method that finds the file for a given image ID, and outputs the raw content of it with a
    // suitable header, e.g. so that <img src="/image/ID" /> will work.
    // This is necessary because image files are stored above the web root, so have no direct URL.
    public function showImage($id, $thumb)
    {
        global $f3;
        $mapper = new DB\SQL\Mapper($f3->get('DB'), $this->table); // create DB query mapper object
        $mapper->load(['id=?', $id]);                              // load DB record matching the given ID
        $fileToShow = (($thumb) ? $mapper['thumbnail'] : $mapper['filepath']);
        $fileType   = (($thumb) ? 'image/jpeg' : $mapper['type']);    // thumb is always jpeg
        header('Content-type: ' . $fileType);               // write out the image file http header
        readfile($fileToShow);                                     // write out raw file contents (image data)
    }

    //------------------------------------------------------------------------------------------------------------------

    // Create the name of the thumbnail file for the given image file
    // -- just by adding "thumb-" to the start, but bearing in mind that it
    // will always be a .jpg file.
    private function thumbFile($picfile)
    {
        return 'thumb-'.pathinfo($picfile, PATHINFO_FILENAME).'.jpg';
    }

    //------------------------------------------------------------------------------------------------------------------
    // This creates the actual thumbnail by resampling the image file to the size given by the thumbsize variable.
    // We can easily change this.  PHP has very rich image processing functionality; this is a simple example.
    // Based on code from PHP manual for imagecopyresampled()
    // NB this is old code: most of these functions also have F3 wrappers, which might be neater here ...
    private function createThumbnail($filename, $thumbfile, $type)
    {
        // Set a maximum height and width
        $width  = $this->thumbsize;
        $height = $this->thumbsize;

        // Get new dimensions
        list($width_orig, $height_orig) = getimagesize($filename);

        $ratio_orig = $width_orig / $height_orig;

        if ($width / $height > $ratio_orig) {
            $width = $height * $ratio_orig;
        } else {
            $height = $width / $ratio_orig;
        }

        // Resample
        $image_p = imagecreatetruecolor($width, $height);
        switch ($type)
        {
            case 'jpeg':
                $image = imagecreatefromjpeg($filename);
                break;
            case 'png':
                $image = imagecreatefrompng($filename);
                break;
            case 'gif':
                $image = imagecreatefromgif($filename);
                break;
            default:
                $data  = file_get_contents($filename);
                $image = imagecreatefromstring($data);
        }
        imagecopyresampled($image_p, $image, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);

        // Output
        // Notice this is always a jpeg image.  We could also have made others, but this seems OK.
        imagejpeg($image_p, $thumbfile);
    }

    static private function setUploadFailMessage($reason)
    {
        return "Upload failed! (" .$reason. ") <a href=''>Return</a>";
    }
}
