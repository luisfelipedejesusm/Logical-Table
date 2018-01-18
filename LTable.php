<?php 

namespace App\CustomClasses\LogicTable;

class LTable {

	// This var is used to store the expressions as variables for further usage
	private $vars_r;

	// Expression String
	protected $exp = null;

	// Sibgle vars. This just capture the globar vars used in the expression.
	private $vars = array();

	// This does the same job as $vars just that this store the negatives one (if exist)
	private $vars_negative = array();

	// Result header for table creation
	private $result = ""; 

	// Error message in case of some sort of user mistake or error in expression. 
	// NOTE: there are some errors 
	// that i havent catch doe to debugging.
	private $error_msg;

	// Array of special characters used in the expressions. 
	// These are the logical operators alowed in the
	// expression. 
	// [ & ] => AND
	// [ | ] => OR
	// [ > ] => IF THEN
	// [ = ] => ONLY IF
	private $specialChars = array('&','|','>','=');


	// Expression setter. It also replace every blank space from string 
	// before assigning it to the Expression var.
	public function with($exp = null){
		$this->exp = str_replace(' ', '', $exp);
		return $this;
	}

	// Simple Validation Expression Method
	private function validate(){
		if (empty($this->exp) || is_null($this->exp)) {
			$this->error_msg = "Expression not specified";
			return false;
		}
		if (preg_match('/[^a-zA-Z&>=\|~)(]/', $this->exp) > 0) {
			$this->error_msg = "Expression contains not supported chars";
			return false;
		}
		if ($this->continuousOperators() || $this->continuousLetter()) {
			$this->error_msg = "Invalid Expression";
			return false;
		}
		if ($this->checkParentheses()) {
			$this->error_msg = "Parentheses Missmatch";
			return false;
		}
		return true;
	}

	// This function checks for continuous special characters.
	private function continuousOperators(){
		foreach(str_split($this->exp) as $key => $str)
			if (in_array($str, $this->specialChars) && in_array(str_split($this->exp)[$key-1], $this->specialChars)) {
				return true;
		}
		return false;
	}

	// This method checks for continuous variables without a operator between
	private function continuousLetter(){
		foreach(str_split($this->exp) as $key => $str)
			if ($key == 0) continue;
			if (preg_match('/^[a-zA-Z]+$/', $str) == 1 && preg_match('/^[a-zA-Z]+$/', str_split($this->exp)[$key-1]) == 1) {
				return true;
		}
		return false;
	}

	// This method chekes that all parentheses are correct, open and close as desired.
	private function checkParentheses(){
		$p_c = 0;
		foreach(str_split($this->exp) as $key => $str) {
			if ($str == '(') $p_c++; else if($str == ')') $p_c--;
		}
		if($p_c != 0) return true;
		return false;
	}

	// Public get method. this returns a <table> html object when called.
	//It firsts print the normal and negatives vars into the table header. 
	// then do some calculations to get the table data and at the end in 
	// calculates the final result and create the table.
	public function get(){
		return $this->validate() ? $this->printVars()->printArrayP($this->getIndex($this->exp))->calcFunction($this->exp)->createTable() : $this->error_msg;
	}

	// Method to create the <table> object using the captured data.
	private function createTable(){
		$cant_v = count($this->vars);
		$cant_r = pow(2, $cant_v);
		$table = "<table class='table table-bordered'><thead>$this->result</thead><tbody>";
		for ($i=0; $i < $cant_r; $i++) { 
			$table .= "<tr>";
			foreach ($this->vars_r as $var) {
				$table .= "<td class='".($var[$i] == true? 'v' : 'f')."'>".($var[$i] == true? 'V' : 'F')."</td>";
			}
			$table .= "</tr>";
		}
		return $table .= "</tbody></table>";
	}

	// This function is used to select the start and end position of 
	// the parentheses of a sub expression in the expression parameter.
	// It stores the start and end in an array for each sub expression,
	// Example: given the expression (A & B) | A > (A = B) it captures the start and end position of
	// (A & b)
	// (A = B) 
	private function getIndex($exp){
		$match = false;
		$after_matches = 0;
		$match_index_start = array(); $match_index_end = array();

		foreach (str_split($exp) as $key => $char) {
		  	if($char == '('){
		  		if ($match == true) {
		  			$after_matches++;
		  		}else{
			  		$match = true;
			  		array_push($match_index_start, $key);
		  		}
		  	}
		  	if ($char == ')') {
		  		if ($after_matches > 0) {
		  			$after_matches--;
		  		}else{
		  			$match = false;
			  		array_push($match_index_end, $key);
		  		}
		  	}
	  	}
	  	return $this->checkIndexLength($match_index_start, $match_index_end, $exp);
	}

	// This method confirms that the start and end position are equal. 
	// That means the sub expression was taken correctly without 
	// leaving any important part behind
	private function checkIndexLength($mis, $mie, $exp){
		if (count($mis) <> count($mie))
			return true;
		$pc = count($mis);
		return $this->getParentheses($mis, $mie, $exp);
	}

	// This function gets the sub expression that was defined in method [ getIndex ]
	// and returns the inside values.
	// If the sub expression has a sub expression it calls back [ getIndex ]
	// which will do a recursive loop ultil all sub expressions are
	// separated as single expressions and pushed to the returning array
	private function getParentheses($mis, $mie, $exp){
		$data = array();
		for ($i=0; $i < count($mis); $i++) { 
			$n_exp = substr($exp, $mis[$i] + 1, $mie[$i] - $mis[$i] - 1);
			if ($this->hasParentheses($n_exp))
	  			array_push($data, $this->getIndex($n_exp));
  			array_push($data, $n_exp);
	  	}
	  	return $data;
	}

	// This function evaluates if the sub expression contains another sub expression
	private function hasParentheses($str){
		foreach (str_split($str) as $s) {
			if ($s == '(' || $s == ')') return true;
		}
		return false;
	} 

	// This expression is a custom workarround to get the expression inside parentheses
	// to use it as a variable.
	// Exmple: A | ~(B | C) & (A & B) will return and array containing:
	// [1] ~(B | C) [2] A & B
	private function customPregMatch($string){
		$data = array();
		$p = 0;
		$start = 0;
		$end;
		$last = false;
		$neg_f = false;
		foreach (str_split($string) as $key => $str) {
			switch ($str) {
				case '&':
				case '|':
				case '=':
				case '>':
					if ($p == 0) {
						$end = $key;
						array_push($data, substr($string, $start, $end - $start));
						array_push($data, $str);
						$start = $key + 1;
					}
					break;
				case '(':
					$p++;
					break;
				case ')':
					$p--;
					if ($p == 0) {
						$end = $neg_f? $key + 1 : $key;
						$neg_f = false;
						array_push($data, substr($string, $start + 1, $end - $start - 1));
						$start = $key + 1;
						$last = true;
					}
					break;
				case '~':
					if(str_split($string)[$key + 1] == '('){
						if($neg_f == false) {
							$start = $key - 1;
							$neg_f = true;
						}
					}
					break;				
				default:
					$last = false;
					break;
			}
		}
		if ($last == false) {
			$end = $key;
			array_push($data, substr($string, $start, $end - $start + 1));
		}
		return array_filter($data);
	}

	// As the name point, it just add spaces between each char before printing it to the header
	private function addSpacesBetweenChars($str){
		return implode(' ',str_split($str));
	}

	// This is a recursive method that calls function [ calcFunction ] which calculates the values
	// of the logic table. it is recursive because it calls itselft if the given parameter
	// contains an inner array
	private function printArrayP($array){
		foreach ($array as $key => $arr) {
			if(is_array($arr)){
				$this->printArrayP($arr);
			}else{
				$this->calcFunction($arr);
			}
		}
		return $this;
	}

	// This method prints the header vars
	private function printVars(){
		foreach ($this->getVars() as $vars) {
			$this->result .= "<th>{$this->addSpacesBetweenChars($vars)}</th>";
		}
		foreach ($this->getVarsNegative() as $vars) {
			$this->result .= "<th>{$this->addSpacesBetweenChars($vars)}</th>";
		}
		return $this;
	}

	// This method get the Generla vars from the initial expression
	private function getVars(){
		foreach (str_split($this->exp) as $key => $char) {
			if(preg_match('/^[a-zA-Z]+$/', $char) == 1)
				if (!in_array($char, $this->vars)) 
					array_push($this->vars, $char);
		}
		$cant_v = count($this->vars);
		$cant_r = pow(2, $cant_v);
		$exp_c = 2;
		foreach ($this->vars as $var) {
			$cant_vf = $cant_r / $exp_c;
			$f_c = 0;
			$is_v = true;
			$d_v = array();

			for($i = 0; $i < $cant_r; $i++){
				array_push($d_v, $is_v);
				if(++$f_c == $cant_vf) {
					if($is_v == true) $is_v = false; else $is_v = true;
					$f_c = 0;
				}
			}
			$this->vars_r[$var] = $d_v;
			$exp_c *= 2;
		}
		return $this->vars;
	}

	// This method do the same, just that only for the negative ones
	private function getVarsNegative(){
		foreach (str_split($this->exp) as $key => $char) {
			if(preg_match('/^[a-zA-Z]+$/', $char) == 1){
				if ($key > 0 && str_split($this->exp)[$key-1] == '~') 
					if (!in_array($char, $this->vars_negative)) 
						array_push($this->vars_negative, "~$char");
			}
		}
		foreach($this->vars_negative as $var){
			$this->vars_r[$var] = array_reverse($this->vars_r[str_split($var)[1]]);
		}
		return $this->vars_negative;
	}

	// This method recives an expression and converts it into code. Then evaluate the code and output the result.
	private function calcFunction($exp){
		$data = $this->customPregMatch($exp);
		$cant_v = count($this->vars);
		$cant_r = pow(2, $cant_v);
		$arr2 = array();
		for($i = 0; $i < $cant_r; $i++){
			$code = "";
			foreach ($data as $d) {
				switch ($d) {
					case '&':
						$code .= ' && ';
						break;
					case '|':
						$code .= ' || ';
						break;
					case '>':
						$code .= ' <= ';
						break;
					case '=':
						$code .= ' == ';
						break;						
					default:
						$code .= '$this->vars_r["'.$d.'"]['.$i.']';
						break;
				}
			}
			array_push($arr2, eval("return $code;"));
		}
		$this->vars_r[$exp] = $arr2;
		$this->result .= "<th>{$this->addSpacesBetweenChars($exp)}</th>";
		if ( $this->hasNegative($exp)){
			$this->vars_r["~($exp)"] = array_map(function($a){
				return !$a;
			}, $arr2);
			$this->result .= "<th>{$this->addSpacesBetweenChars("~($exp)")}</th>";
		}
		return $this;
	}

	// this method just check if a given expression contains a negative part in the main expression
	private function hasNegative($exp){
		$f_exp = "~($exp)";
		if (strpos($this->exp, $f_exp) !== false) return true; else return false; 
	}
}