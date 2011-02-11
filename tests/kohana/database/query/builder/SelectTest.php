<?php

require_once Kohana::find_file('tests','kohana/DatabaseTest');

class Kohana_Database_Query_Builder_SelectTest extends Kohana_DatabaseTest{

	public function provider_select(){
		return array(
			array(array(),'SELECT *'),
			array(array('col1','col2','col3'),'SELECT "col1", "col2", "col3"'),
			array(array('table1.col1','table2.col2','table2.col3'),'SELECT "table1"."col1", "table2"."col2", "table2"."col3"')

			);

	}

	/**
	 * Tests Kohana_Database_Query_Builder_Select::test_select() 
	 *
	 * @test
	 * @dataProvider provider_select
	 * @param array  $cols  select columns  
	 * @param string $expected the expected result of the compile 
	 */
	public function test_select(array $select, $expected){
		$select = $this->_select($select);	

		$sql = $select->compile($this->getMockDatabase());

		$this->assertEquals($expected,$sql);

	}

	public function provider_select_from(){
		return array(
				array(array(),array('foobar'),'SELECT * FROM "foobar"'),
				array(array('col1','col2','col3'),array(array('foo','bar')),'SELECT "col1", "col2", "col3" FROM "foo" AS "bar"'),
				array(array('table1.col1','table2.col2','table2.col3'), array(array('foo','bar'),array('bam','baz') ),'SELECT "table1"."col1", "table2"."col2", "table2"."col3" FROM "foo" AS "bar", "bam" AS "baz"')

			);

	}

	/**
	 * Tests Kohana_Database_Query_Builder_Select::test_select() 
	 *
	 * @test
	 * @dataProvider provider_select_from
	 * @param array  $cols  select columns  
	 * @param mixed  $from  from, could be string representing table, could be an array representing table and alias or could be multiple array of multiple tables
	 * @param string $expected the expected result of the compile 
	 */
	public function test_select_from(array $select,array $froms,$expected,$multiple = false){
		$select = $this->_select($select);
		$this->_from($select,$froms);	

		$sql = $select->compile($this->getMockDatabase());

		$this->assertEquals($expected,$sql);

	}

	public function provider_select_from_where(){
		return array(
			array(
				array(),
				array('foobar'),
				array(array('baz','=','bam')),
				'SELECT * FROM "foobar" WHERE "baz" = \'bam\''
			),
			array(
				array('col1','col2','col3'),
				array(array('foo','bar')),
				array('where',array('bar.col1','>',10),'where',array('bar.col2','IN',array(1,2,3)) ),
				'SELECT "col1", "col2", "col3" FROM "foo" AS "bar" WHERE "bar"."col1" > 10 AND "bar"."col2" IN (1, 2, 3)'
			),
			array(
				array(),
				array('gah'),
				array('where_open','where',array('col1','=','foo'),'or_where',array('col2','=',5),'where_close','and_where',array('col3','=',1)),
				'SELECT * FROM "gah" WHERE ("col1" = \'foo\' OR "col2" = 5) AND "col3" = 1'
			)

		);

	}

	/**
	 * Tests Kohana_Database_Query_Builder_Select::test_select() 
	 *
	 * @test
	 * @dataProvider provider_select_from_where
	 * @param array  $cols  select columns  
	 * @param array  $from  tables 
	 * @param array  $where  select columns  
	 * @param string $expected the expected result of the compile 
	 */
	public function test_select_from_where(array $select, array $froms, array $where,$expected){
		$select = $this->_select($select);
		$this->_from($select,$froms);
		$this->_where($select,$where);

		$sql = $select->compile($this->getMockDatabase());

		$this->assertEquals($expected,$sql);
	}

	public function provider_select_from_join(){
		return array(
				array(
				array(),
				array('foobar'),
				array(
					array('bam',array(array('baz','=','bam'))),
				),
				'SELECT * FROM "foobar" JOIN "bam" ON "baz" = "bam"'
			),
			array(
				array('col1','col2','col3'),
				array(array('foo','bar')),
				array(
					array('gah',array('on',array('bar.col1','=','gah.col2'),'on',array('bar.col2','IN',array(1,2,3)) ))
				),
				'SELECT "col1", "col2", "col3" FROM "foo" AS "bar" JOIN "gah" ON "bar"."col1" = "gah"."col2" AND "bar"."col2" IN (1, 2, 3)'
			),
			array(
				array(),
				array('gah'),
				array(
					array('foo',array('on_open','on',array('col1','=','foo'),'or_on',array('col2','=',5),'on_close','on',array('col3','=',1))),
				),
				'SELECT * FROM "gah" JOIN "foo" ON ("col1" = "foo" OR "col2" = 5) AND "col3" = 1'
			)

		);

	}

	/**
	 * Tests a select from join statement for Kohana_Database_Query_Builder_Select
   * 
   * @test
   * @dataProvider provider_select_from_join
   * @param array $select
   * @param array $froms
   * @param array $joins
	 * @param string $expected
   */
	public function test_select_from_join(array $select,array $froms,array $joins,$expected){
		$select = $this->_select($select);
		$this->_from($select,$froms);
		$this->_join($select,$joins);

		$sql = $select->compile($this->getMockDatabase()); 

		$this->assertEquals($expected,$sql);


	}
	protected function _join(Kohana_Database_Query_Builder_Select $select,array $joins){
			foreach($joins as $join){
				$select->join($join[0]);
				$this->_condition_helper($select,$join[1],'on');

			}
			
		return $select;
	}

	/**
	 * applies a set of from statements to the $select object
	 * 
	 * this method supports $wheres in the format of array('func',array(...args...),'func','func',array(....args...),'func')
	 * if the $wheres[0] is an array it assumes your wanting to call ->where() with the args in $wheres[0]
	 *
	 * e.g. $wheres = array(array('col1','=',5),'or_where',array('col2','IN',array(1,2,3)))
	 * 								WHERE "col1" = 5 OR "col2" IN (1,2,3)
	 *
	 * e.g.	$wheres = array('where_open','where',array('col1','=','foo'),'or_where',array('col2','=',5),'where_close','and_where',array('col3','=',1));
	 * 								WHERE ( "col1" = 'foo' OR "col2" = 5 ) AND "col3" = 1
	 *
	 * @param Kohana_Database_Query_Builder_Select $select
	 * @param array $wheres
	 * @return Kohana_Database_Query_Builder_Select
	 */
	protected function _where(Kohana_Database_Query_Builder_Select $select,array $wheres){
		return $this->_condition_helper($select,$wheres,'where');	

	}


	/**
	 * helper method for _where and _join, compiles a list of commands in $conditions together and applies them to the $object
	 *
	 * @param Kohana_Database_Query_Builder $object, an instance of Kohana_Database_Query_Builder_Select or Kohana_Database_Query_Builder_Join
	 * @param array, $conditions, a list of commands
	 * @param string $default, 'where'|'on'
	 * @return Kohana_Database_Query_Builder
	 *
	 */
	private function _condition_helper(Kohana_Database_Query_Builder $object,array $conditions,$default){
		$reflect = new ReflectionClass($object);
		for($i = 0; $i<count($conditions);$i++){
			//if the current argument is a string and is a method of Kohana_Database_Query_Builder then apply the method to the object
			if(is_string($conditions[$i]) && $reflect->hasMethod($conditions[$i])){
				$func = $reflect->getMethod($conditions[$i]);
				$args =	array();

				if(!is_string($conditions[$i+1]) || (is_string($conditions[$i+1]) && !$reflect->hasMethod($conditions[$i+1]))){
					$args = $conditions[$i+1];
					$i++;
				}	

				$func->invokeArgs($object,$args);

			}
			else if(is_array($conditions[$i])){
				$func = $reflect->getMethod($default)->invokeArgs($object,$conditions[$i]); 

			}

		}

		return $object;
	}



	/**
	 * applies a set of from statements to the $select object
	 *
	 * @param Kohana_Database_Query_Builder_Select
	 * @param array $froms
	 * @return Kohana_Database_Query_Builder_Select
	 */
	protected function _from(Kohana_Database_Query_Builder_Select $select,array $froms){
		foreach($froms as $from){
			$select->from($from);
		}

		return $select;


	}


	/**
	 * Tests to make sure for the given $select that the cols passed via the constructor result in the same when passed into the select_array method
	 *
	 * @param array
	 * @return Kohana_Database_Query_Builder_Select
	 */
	protected function _select(array $select){
			// construct our fist select
			$select1 = DB::select(); 
			$select_constructor = new ReflectionMethod('Kohana_Database_Query_Builder_Select','__construct');
			$select_constructor->invokeArgs($select1,array($select));

			// construct the second select, this one is easier, just construct it normally and pass $select via select_array()
			$select2 = DB::select()->select_array($select);

			// get $this->_select for each respective select object
			$select1_prop = new ReflectionProperty($select1,'_select');
			$select1_prop->setAccessible( true ); 
			$select2_prop = new ReflectionProperty($select2,'_select');
			$select2_prop->setAccessible( true ); 

			// make sure the $select1->_select and $select2->_select are equal
			$this->assertEquals($select1_prop->getValue($select1),$select2_prop->getValue($select2));

			// return an the first select, so it could be used for a more complex select statement
			return $select1;

	}


	public function provider_whole_select(){
		return array(
			array(
					array('col1','col2','col3'),//select 
					array('foo','bar'),//from
					array(
						array('join_table1','on',array('join_table1.col1','=','bar.col1'),'on',array('join_table.col2','=','bar.col2')),
						array(array('join_table2','jt2'),'on',array('jt2.col2','=','bar.col2'))
					),//joins
					array(),//where
					array(),//group
					array(),//order
					'SELECT "col1", "col2", "col3" FROM "foo" AS "bar" JOIN "join_table1" ON "join_table1"."col1" = "bar"."col1" AND "join_table"."col2" = "bar"."col2" JOIN "join_table2" AS "jt2" ON "jt2"."col2" = "bar"."col2"'

				)
			);
	}

	/**
	 * Tests Kohana_Database_Query_Builder_Select::test_whole_select() 
	 *
	 * @test
	 * @dataProvider provider_whole_select
	 * @param array  $cols  select columns  
	 * @param array  $table table to apply from 
	 */
	public function test_whole_select(array $select,array $from,array $joins, array $where,array $group, array $order, $expected){
		$db = $this->getMockDatabase();
		//create an instance of Database_Query_Builder_Select, dupping the compile method with null funcitonality, passing the select arguments
		$query = DB::select()->select_array($select);//$this->getMock('Database_Query_Builder_Select',,array($select));
		$this->assertAttributeSame($select, '_select', $query);//assert the select array was applied to the object

		$query->from($from);
		$this->assertAttributeSame(array($from), '_from', $query);//assert the from array was applied to the object

		//$this->_apply_joins($query,$joins);
		//$this->assertAttributeSame($joins,'_join',$query);
		

		$sql = $query->compile($db);

		//$this->assertEquals($expected,$sql);

	}





}



?>
