<?php

namespace PTA;
use \PDO;

class Admin {

	public static function getAnnouncement($id){

		$sql=<<<SQL
		SELECT * FROM announcements WHERE id=:id
SQL;

		$stmt= PdoFactory::getInstance()->prepare("SELECT * FROM announcements WHERE id=:id");

		$stmt -> bindValue(':id', $id, PDO::PARAM_INT);
		$stmt->execute();

		$temp=null;
		$temp=$stmt->fetchAll(PDO::FETCH_ASSOC);
		return $temp[0];

	}

	public static function setStickiness($id,$stickiness){
	    $sql="UPDATE announcements SET sticky = :stickiness WHERE id=:id";
	    $stmt=PdoFactory::getInstance()->prepare($sql);
	    $stmt->bindValue('id',	    $id,	PDO::PARAM_INT);
	    $stmt->bindValue('stickiness',  $stickiness,PDO::PARAM_INT);
	    $success=$stmt->execute();
	}

	public static function getListAnnouncement() {

		$sql = null;

		$sql=<<<SQL
	SELECT * FROM announcements ORDER BY created_on DESC
SQL;

		$stmt = PdoFactory::getInstance()->prepare($sql);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public static function getLesson ($id){

		$sql = null;

		$sql=<<<SQL
	SELECT * FROM lesson WHERE id = :id ORDER BY level
SQL;

		$stmt = PdoFactory::getInstance()->prepare($sql);
		$stmt->bindValue(':id', $id);
		$stmt->execute();

		$temp=null;
		$temp=$stmt->fetchAll(PDO::FETCH_ASSOC);
		return $temp[0];
	}

/**
 *  Get a record of a lesson by the foreign key<br>
 *  The foreign key that should be lesson_id instead of "lesson"
 *
 */
	public static function getLessonByLessonNum ($lesson_num,$level_id){

		$sql = null;

		$sql=<<<SQL
	SELECT * FROM lesson WHERE lesson = :lesson_num AND level = :level_id ORDER BY level
SQL;

		$stmt = PdoFactory::getInstance()->prepare($sql);
		$stmt->bindValue(':lesson_num', $lesson_num);
		$stmt->bindValue(':level_id', $level_id);
		$stmt->execute();

		$temp=null;
		$temp=$stmt->fetchAll(PDO::FETCH_ASSOC);
		return $temp[0];
	}


	public static function getListLesson ($level=null){

		$sql = null;
		if($level!=null){$sql="SELECT * FROM lesson WHERE level=:level ORDER BY lesson";}
		else{$sql="SELECT * FROM lesson ORDER BY lesson";}

		$stmt = PdoFactory::getInstance()->prepare($sql);
		$stmt->bindValue(':level', $level);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}


/**
 * it returns either a RECORD as an ARRAY
 * or a SET OF RECORDS
 * or false
 * 
 * 
 */	
	
	public static function getResultsSQL ($sql=null,$values=[]){

		$temp=null;
		$v=null;

		$stmt = PdoFactory::getInstance()->prepare($sql);

		if(!empty($values)){
			foreach($values as  $v){

				$stmt->bindValue( $v[0], $v[1], $v[2] );
			}
		}
		
		$stmt->execute();

		$temp=$stmt->fetchAll(PDO::FETCH_ASSOC);

		if(count($temp>=1)){
			return $temp;
		}elseif(empty($temp)){
			return false;
		}
	}

	public static function getVideo (){

		$sql = null;

		$sql="SELECT * FROM video ORDER BY lesson";

		$stmt = PdoFactory::getInstance()->prepare($sql);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public static function getListFAQ (){
		
		// feching all faq records and constructing the data structure to be passed on to the template


		$sql="SELECT * FROM faq_category ORDER BY num";
		$stmt=  PdoFactory::getInstance()->prepare($sql);
		$stmt->execute();
		$listCat=$stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach($listCat as $key => $cat){

			$sqlq="SELECT * FROM faq_question WHERE cat_id=:cat_id";
			$stmtq= PdoFactory::getInstance()->prepare($sqlq);
			$stmtq->bindValue(':cat_id',$cat['id'],PDO::PARAM_INT);
			$stmtq->execute();
			$tempListQ=$stmtq->fetchAll(PDO::FETCH_ASSOC);

			foreach($tempListQ as $qkey => $que){

				$sqla="SELECT * FROM faq_answer WHERE que_id=:que_id";
				$stmta=PdoFactory::getInstance()->prepare($sqla);
				$stmta->bindValue(':que_id',$que['id'],PDO::PARAM_INT);
				$stmta->execute();
				$tempListA=$stmta->fetchAll(PDO::FETCH_ASSOC);

				$tempListQ[$qkey]['answ']=$tempListA;
			}

			$listCat[$key]['que']=$tempListQ; //*******************
		}
		
		return $listCat;
	}
	
	public static function getListFAQ_category(){
		
		$sql="SELECT * FROM faq_category ORDER BY num";
		$stmt=  PdoFactory::getInstance()->prepare($sql);
		$stmt->execute();
		$listCat=$stmt->fetchAll(PDO::FETCH_ASSOC);
		return $listCat;
	
	}
	
	public static function getListRelatedFAQ_question($cat_id){
		
		$sqlq="SELECT * FROM faq_question WHERE cat_id=:cat_id";
		$stmtq= PdoFactory::getInstance()->prepare($sqlq);
		$stmtq->bindValue(':cat_id',$cat['id'],PDO::PARAM_INT);
		$stmtq->execute();
		$tempListQ=$stmtq->fetchAll(PDO::FETCH_ASSOC);
		return $tempListQ;
	}
	
}// end of all things ADMIN CLASS
