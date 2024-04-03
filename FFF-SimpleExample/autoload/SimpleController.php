<?php
// Class that provides methods for working with the form data.
// There should be NOTHING in this file except this class definition.

class SimpleController {
	private $mapper;

	public function __construct($table)
    {
		global $f3;						// needed for $f3->get()
		$this->mapper = new DB\SQL\Mapper($f3->get('DB'), $table);	// create DB query mapper object
	}

	public function putIntoUserDatabase($data)
	{
		$this->mapper->username   = $data["username"];
        $this->mapper->email   = $data["email"];
		$this->mapper->password = $data["password"];
		$this->mapper->save();					 // save new record with these fields
	}

	public function getData()
    {
		return $this->mapper->find();
	}

	public function deleteFromUserDatabase($idToDelete)
    {
		$this->mapper->load(['id=?', $idToDelete]); // load DB record matching the given ID
		$this->mapper->erase();						// delete the DB record
	}

    public function getAllBeetleData($name)
    {
        $list = $this->mapper->load(["family_name LIKE ?", "%" . $name . "%"]);
        if($list == false) {
            throw new Exception('Database query failed.');
        }
        return $list;
    }

    public function getCommentData($name)
    {
        $list = $this->mapper->load(["beetle_name LIKE ?", "%" . $name . "%"]);
//        if($list == false) {
//            throw new Exception('Database query failed.');
//        }
        return $list;
    }

    public function getAllUserData($name)
    {
        $list = $this->mapper->load(["username LIKE ?", "%" . $name . "%"]);
        if($list == false) {
            throw new Exception('Database query failed.');
        }
        return $list;
    }
}