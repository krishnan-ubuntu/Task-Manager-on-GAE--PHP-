<?php
//error_reporting(0);

use google\appengine\api\users\User;
use google\appengine\api\users\UserService;
use google\appengine\api\mail\Message;

require_once 'src/Google_Client.php';
require_once 'src/contrib/Google_TasksService.php';


$user = UserService::getCurrentUser();


$task_done = $_POST['done'];
$task_to_remove = $_POST['sel'];
$selected_task_used_for_reassigning = $_POST['selectedvalue'];
$new_task_owner = $_POST['newtaskowner'];
$checked_task = $_POST['checkedvalue'];

$logged_user_team_id = NULL;
$logged_user_team_name = "";

echo $selected_task_used_for_reassigning;
echo $new_task_owner;



function open_database_connection() //Opens database connection
	{
		$dbhost = 'localhost';
		$dbuser = '******';
		$dbpass = '******';
		$db = 'doeverything';
		$db_con = mysql_connect($dbhost, $dbuser, $dbpass, $db);
		mysql_select_db('doeverything');
		//syntax for mysql connection - mysqli_connect(host,username,password,dbname);
		//$db_con = mysqli_connect("localhost","todolist","11test11","todolist");
		return $db_con;	
	}

	function close_database_connection($db_con) //Closes database connection
	{
    	mysql_close($db_con);
	}

if ($user) 
{
	/*
	  	echo 'Hello, ' . $logged_user_name;
	  	echo "<br>";
	  	header('Location: ' . UserService::createLoginURL($_SERVER['REQUEST_URI']))
  	*/

  	$logged_user_name = htmlspecialchars($user->getNickname());
	
	$db_connection = open_database_connection();

	mysql_select_db('doeverything');

	/*
		--------------------------------------------------------
		If user name is not in user table then insert in user table post which team will be created
	*/


	$sql2 = "SELECT * FROM user_team_table";

	$user_table_val = mysql_query($sql2, $db_connection);

	while($row_of_user_table = mysql_fetch_assoc($user_table_val))
	{
		$is_user_their_in_table = $row_of_user_table['user_name'];

		if ($is_user_their_in_table != $logged_user_name)
		{
			$sql1 = "INSERT INTO user_team_table (user_name) VALUES('$logged_user_name')";
			mysql_query($sql1, $db_connection);
			break;
		}
		else
		{
			break;
		}
	}

	/*
		--------------------------------------------------------
	*/


	$sql = "SELECT userid, teamid, team_name FROM user_team_table WHERE user_name = '$logged_user_name'";

	global $logged_user_team_id;
	global $logged_user_team_name;
    
    $retval = mysql_query($sql, $db_connection);

    while($row = mysql_fetch_assoc($retval))
	{
		$logged_user_id = $row['userid'];
		$logged_user_team_id = $row['teamid'];
		$logged_user_team_name = $row['team_name'];

	}

	close_database_connection($db_connection);

}

else 
{
	header('Location: ' . UserService::createLoginURL($_SERVER['REQUEST_URI']));
}




function add_to_do_list() 
{	
	
	$new_task = $_POST['entertask'];

	global $logged_user_name;
	global $logged_user_team_id;
	global $logged_user_team_name;



	$db_connection = open_database_connection();

	mysql_select_db('doeverything');
	$sql = "INSERT INTO tasks (Name, user_name) VALUES ('$new_task', '$logged_user_name')";
	mysql_query($sql, $db_connection);
	close_database_connection($db_connection);

}

function add_new_reject_duplicate()
{
	global $logged_user_name;
	$new_task = $_POST['entertask'];
	
	$db_connection = open_database_connection();

	$add_task = true;	

	$sql = "SELECT * FROM tasks WHERE user_name = '$logged_user_name'";
	mysql_select_db('doeverything');

	$retval = mysql_query( $sql, $db_connection );
	if(! $retval )
	{
  		die('Could not get data: ' . mysql_error());
	}

	while($row = mysql_fetch_assoc($retval))
	{
		$existing_task =  $row['Name'];
	
    	if ($existing_task == $new_task) 
		{
			$add_task = false;
			$error_msg = "<br>The task that you entered already exists.";
			echo '<font color="red">'.$error_msg.'</font><br><br>';
			break;
		
		}
 	}
	if($add_task){
		add_to_do_list();
		$success_msg = "<br>The task has been successfully added.";
		echo '<font color="red">'.$success_msg.'</font><br><br>';
	}
	close_database_connection($db_connection);
}

function display_tasks_from_table() //Displayes existing tasks from table
{
	global $logged_user_name;
	global $selected_task_from_ui;
	global $logged_user_type;
	
	$db_connection = open_database_connection();

	//$sql = 'SELECT id, name FROM todolist';
	$sql = "SELECT * FROM tasks WHERE user_name = '$logged_user_name'";

	mysql_select_db('doeverything'); //Choosing the db is paramount

	$retval = mysql_query( $sql, $db_connection);
	if(! $retval )
	{
  		die('Could not get data: ' . mysql_error());
	}
	while($row = mysql_fetch_assoc($retval))
	{
    
    	echo "<input class='checkbox' type='checkbox' name='checkboxes{$row['task_id']}' value='{$row['Name']}' onclick='respToChkbox()' >{$row['Name']} <br>";
    
    } 
	
	close_database_connection($db_connection);
	}

function display_done_tasks_from_table()
{
	global $logged_user_name;
	
	$db_connection = open_database_connection();

	$sql = "SELECT * FROM donetasks WHERE user_name = '$logged_user_name'";

	mysql_select_db('doeverything'); //Choosing the db is paramount

	$retval = mysql_query( $sql, $db_connection);
	if(! $retval )
	{
 		 die('Could not get data: ' . mysql_error());
	}

	echo "<form class='showdonetasks' name='showdonetasks' action='' method='post' >";
	while($row = mysql_fetch_assoc($retval))
	{
		echo "<img src='http://doeverything.in/friendsonlyrelease/images/i_check.gif' alt='tick mark' />{$row['Name']}<br>";
    } 
    echo "</form>";
    close_database_connection($db_connection);
    echo "<br>";
    echo "<br>";

}

$task_done = $_POST['done'];


function task_is_done() //Mark a task as done
{
	$db_connection = open_database_connection();
	$move_task = false;
	global $task_done;
	global $logged_user_name;
	global $today_date;

	mysql_select_db('doeverything');
	//$sql = "DELETE FROM todolist WHERE name = "."'".$task_done."'";
	//$sql = "DELETE FROM todolist WHERE user_name = '$logged_user_name' AND name = "."'".$task_to_remove."'";
	//$sql = "DELETE FROM todolist WHERE user_name = "."'".$logged_user_name."'" AND "name = "."'".$task_to_remove."'";
	$sql = "DELETE FROM tasks WHERE user_name = '$logged_user_name' AND name = '$task_done'";
	//$sql1 = "INSERT INTO donetasks (name, user_name, completed_on) VALUES ('$task_done', '$logged_user_name', '$today_date')";
	$sql1 = "INSERT INTO donetasks (name, user_name) VALUES ('$task_done', '$logged_user_name')";
	//INSERT INTO donelist (name) VALUES ('xyzzzz')
	if($task_done!='' || $task_done!=null) 
	{
		mysql_query($sql, $db_connection);
		mysql_query($sql1, $db_connection);
		$move_task = true;

	}
	close_database_connection($db_connection);
}

function remove_from_list() //Removes a selected task from DB
{
	$db_connection = open_database_connection();

	global $task_to_remove;
	global $logged_user_name;
	mysql_select_db('doeverything');
	//$sql = "DELETE FROM todolist WHERE user_name = "."'".$logged_user_name."'" AND "name = "."'".$task_to_remove."'";
	$sql = "DELETE FROM tasks WHERE user_name = '$logged_user_name' AND name = '$task_to_remove'";
	if($task_to_remove!='' || $task_to_remove!=null) 
	{
		mysql_query($sql, $db_connection);
	}
	close_database_connection($db_connection);
}

function reassign_selected_task() //Changing the owner of the task
{
	global $selected_task_used_for_reassigning;
	global $logged_user_name;	
	global $new_task_owner;

	$db_connection = open_database_connection();

	mysql_select_db('doeverything');

	$sql = "UPDATE tasks SET user_name = '$new_task_owner', original_owner = '$logged_user_name' WHERE user_name = '$logged_user_name' AND Name = '$selected_task_used_for_reassigning'";

	if ($new_task_owner!='Choose your team member') 
	{
		mysql_query($sql, $db_connection);
		send_email();
	}
	close_database_connection($db_connection);
}


function build_build_team_member_drop_down_menu()
{
	global $logged_user_team_id;
	global $logged_user_name;

	$db_connection = open_database_connection();

	//$sql= "SELECT * FROM tasks WHERE teamid = $logged_user_team_id";

	$sql= "SELECT * FROM user_team_table WHERE teamid = $logged_user_team_id";

	mysql_select_db('doeverything');

	$retval = mysql_query( $sql, $db_connection);


	if(! $retval )
	{
  		die('Could not get data: ' . mysql_error());
	}
	
	echo '<select name="team_name">';
	echo '<option size =30 >Choose your team member</option>'; 

	$dum_var = "";

	while($row = mysql_fetch_assoc($retval))
	{
		$team_member = $row['user_name'];

		if($logged_user_name != $team_member)
		{
			if ($dum_var != $team_member) 
			{
				echo '<option value='.'"'.$team_member.'"'.'>'.$team_member.'</option>';
				$dum_var = $team_member;
			}

		}
	}
			
	echo "</select>";	
		
		

	close_database_connection($db_connection);
}


function create_team_ui()
{

	global $logged_user_team_id;
	global $logged_user_team_name;
    
   	echo "<br><br><br><br>"; 
   	echo "You do not have a team, please create one.";
   	echo "<br>";
   	$logged_user_team_id = rand(); 
   	echo '<form name="submitteam" action="" method="post">';
   	echo '<input type="text" name="enterteam" id="teamntry" onclick="runOnClick()" >';
   	echo '<input type="submit" value="Create Team" name="team" id="team" >';
   	echo '</form>';
   	echo "<br>";
}

function create_team()
{

	global $logged_user_team_id;
	global $logged_user_team_name;
	global $logged_user_name;

	$logged_user_team_name = $_POST['enterteam'];

	$db_connection = open_database_connection();

	mysql_select_db('doeverything');

	//$sql = "INSERT INTO user_team_table (teamid, team_name) VALUES ($logged_user_team_id, '$logged_user_team_name') WHERE user_name = '$logged_user_name'";
	$sql = "UPDATE user_team_table SET teamid = $logged_user_team_id, team_name = '$logged_user_team_name' WHERE user_name = '$logged_user_name'";

	mysql_query( $sql, $db_connection);
	close_database_connection($db_connection);	
	
}

function send_email() //not working as of now.
{

	global $selected_task_used_for_reassigning;
	global $logged_user_name;	
	global $new_task_owner;

	try
	{
	  $message = new Message();
	  $message->setSender("$logged_user_name");
	  $message->addTo("$new_task_owner");
	  $message->setSubject("Task name: $selected_task_used_for_reassigning has been assigned to you");
	  $message->setTextBody("A new task has been assigned to you.");
	  //$message->addAttachment('image.jpg', 'image data', $image_content_id);
	  $message->send();
	} catch (InvalidArgumentException $e) {
  // ...
	}
}

?>
<html>
<head>
	<title>Remember everything | To Do List for your team</title>
	<style type="text/css">
			#tabs ul {
						padding: 0px;
						margin: 0px;
						margin-left: 10px;
						list-style-type: none;
					}

			#tabs ul li {
				display: inline-block;
				clear: none;
				float: left;
				height: 24px;
			}

			#tabs ul li a {
				position: relative;
				margin-top: 16px;
				display: block;
				margin-left: 6px;
				line-height: 24px;
				padding-left: 10px;
				background: #f6f6f6;
				z-index: 9999;
				border: 1px solid #ccc;
				border-bottom: 0px;
				-moz-border-radius-topleft: 4px;
				border-top-left-radius: 4px;
				-moz-border-radius-topright: 4px;
				border-top-right-radius: 4px;
				width: 130px;
				color: #000000;
				text-decoration: none;
				font-weight: bold;
			}

			#tabs ul li a:hover {
				text-decoration: underline;
			}

			#tabs #Content_Area {
				padding: 0 15px;
				clear:both;
				overflow:hidden;
				line-height:19px;
				position: relative;
				top: 20px;
				z-index: 5;
				/*height: 150px;*/
				overflow: hidden;
			}

			p { padding-left: 15px; }
	</style>

<script type="text/javascript">
	function tab(tab) {
		document.getElementById('tab1').style.display = 'none';
		document.getElementById('tab2').style.display = 'none';
		document.getElementById('li_tab1').setAttribute("class", "");
		document.getElementById('li_tab2').setAttribute("class", "");
		document.getElementById(tab).style.display = 'block';
		document.getElementById('li_'+tab).setAttribute("class", "active");
	}

	

</script>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.4/jquery.min.js"></script>
	</head>
<body onload="runOnLoad()">
	<div id="container">
		<div id="header" class="header_pos" ></div>
		<table>
			<tr>
				<td>
					<img src="http://doeverything.in/friendsonlyrelease/images/doEverything_logo.png" alt="doEverything.in logo" /><br>
				</td>
			</tr>
		</table>
	<div id="header" class="form_task_disp_pos" >
		<table border="0">
			<tr>
				<td>
					<form name="submittask" onsubmit="return validateTaskSubmission()" action="" method="post">
						<input type="text" name="entertask" id="taskentry" onclick="runOnClick()" >
						<input type="submit" value="Submit" name="submit" id="submit" >
						<br>
						<!--<input type="text" name="edittask" id="taskedit" onchange="getEditedTask()">
						<button class='but' type='button' name="editbutton" id='edit' onclick='respToEdit()' >Edit</button>-->
						<label for="entertask" id="errorLabel"></label>
					</form>
					<?php
					echo "Welcome ".$logged_user_name."<br>";
					//echo "Team ID: ".$logged_user_team_id."<br>";
					echo "Team Name: ".$logged_user_team_name."<br>";

					?>
					<script type="text/javascript">

						function runOnLoad() 
						{
							document.getElementById('taskentry').value = "Enter your task here";
							//document.getElementById('taskedit').style.visibility = "hidden";
							//document.getElementById('edit').style.visibility = "hidden";
							document.getElementById('teamntry').value = "Enter a team name here";
						}

						function runOnClick() 
						{
							document.getElementById('taskentry').value = "";
							document.getElementById('teamntry').value = "";
						}

						function validateTaskSubmission() //This function validates the adding task form
						{
							var enteredTaskVal = document.getElementById('taskentry').value;
							var taskLength = enteredTaskVal.length;
							
							if (enteredTaskVal === "") 
							{
								document.getElementById('errorLabel').innerHTML = "Please enter a task.".fontcolor("red");
								return false;
							}
							else if (enteredTaskVal === "Enter your task here") 
							{
								document.getElementById('errorLabel').innerHTML = "Please enter a task.".fontcolor("red");
								return false;
							}
							else if (taskLength <= 2) 
							{
								document.getElementById('errorLabel').innerHTML = "The task you entered is too short.".fontcolor("red");
								return false;
							}
						}

					function respToChkbox()
					{
						var inputElements = document.getElementsByTagName('input');
						var input_len = inputElements.length;
						var buttonElements  = document.getElementsByTagName('button');
						var butt_len = buttonElements.length;
						
						for (var i = 0; i<input_len; i++) 
						{
							
							
							if (inputElements[i].checked === true)
							{
								selVal = inputElements[i].value;
								doneVal = inputElements[i].value;
								curVal = inputElements[i].value;
								
							}
						
						
						}
					}

					$(document).ready(function() {   
						$("#donebutton").click(function(){
							$.ajax({
								type: "POST",
								url: "task_list.php", //This is the current doc
								//data: {sel:selVal, remsubmit:"1"},
								data: {done:doneVal},
								success: function(data){
    
									    /*This will be changed using a Ajax function on a later date so that the data is
									    updated without page refresh*/ 
									    confirm("The selected task has been successfully marked as done");
										window.location.reload();
    
						}
						});
						});        
						});

					$(document).ready(function() {   
						$("#rembutton").click(function(){
							$.ajax({
								type: "POST",
								url: "task_list.php", //This is the current doc
								//data: {sel:selVal, remsubmit:"1"},
								data: {sel:selVal},
								success: function(data){
					    			confirm("The selected task has been successfully deleted");
									window.location.reload();

									}
					});
					});        
					});


					$(document).ready(function() {   
						$("#assigntaskbutton").click(function(){
							//var newOwner = $("#persontoassigntask").val();
							var newOwner = $("select").val();
							if (newOwner != 'Choose your team member') {
							$.ajax({
								type: "POST",
								url: "task_list.php", //This is the current doc
								//data: {sel:selVal, remsubmit:"1"},
								data: {selectedvalue:selVal, newtaskowner:newOwner},

								success: function(data){
						    
						    	/*This will be changed using a Ajax function on a later date so that the data is
						    	updated without page refresh*/ 
						    	confirm("The selected task has been successfully assigned");
								window.location.reload();   
						}
					});
						}
					});
					});


					$(document).ready(function() {   
							$.ajax({
								type: "POST",
								url: "task_list.php", //This is the current doc
								//data: {sel:selVal, remsubmit:"1"},
								data: {checkedvalue:selVal},
								success: function(data){
					    			confirm("The selected task has been successfully deleted");
									window.location.reload();

									}
					});       
					});



					</script>

					<?php
					
					

					if($task_done != "")
					{
						task_is_done();

					}

					if ($task_to_remove != "") {
						remove_from_list();
					}

					if ($selected_task_used_for_reassigning != "" && $new_task_owner != "")
						{
							reassign_selected_task();
						}

				?>
					
				<br>
					<hr>
					<div>

					<button class='butt' type='button' id='donebutton' onclick='' >Done</button>
					<button class='butt' type='button' id='rembutton' onclick='' >Remove</button>
					<button class='butt' type='button' id='assigntaskbutton' onclick='' >Assign Task</button>
					<!--<input type='text' id='persontoassigntask' name='persontoassigntask1'><br>-->
					<?php
					if ($logged_user_team_name != "") 
					{
						
						build_build_team_member_drop_down_menu();
					}

					?>

				  </div>
					<script type="text/javascript">

					$(document).ready(function() {
						var $submit = $(".butt").hide();//not working ????
						var $assigntaskperson = $("select").hide(); //not working ????
						var $cbs = $('input[class="checkbox"]').click(function() {
						$submit.toggle( $cbs.is(":checked") );
						$assigntaskperson.toggle( $cbs.is(":checked") );
						});
					});

					</script>
			</tr>
		</table>
		<div id="tabs">
			<ul>
				<li id="li_tab1" onclick="tab('tab1')"><a>Active Tasks</a></li>
				<li id="li_tab2" onclick="tab('tab2')"><a>Done Tasks</a></li>
			</ul>
			<div id="Content_Area">
				<div id="tab1">
					<?php
						if(isset($_POST['submit']))
						{
							add_new_reject_duplicate();
						}

						display_tasks_from_table();

						if ($logged_user_team_id == NULL) 
						{

							if ($logged_user_team_name == NULL || $logged_user_team_name == "") 
							{
								create_team_ui();
							}
						}

						if(isset($_POST['team']))
						{
							create_team();
						}

						
					?>

			</div>

			<div id="tab2" style="display: none;">
				<?php
				display_done_tasks_from_table();
				?>
			</div>
		</div>
	</div>
		
	</div>
	
	<div id="footer">
		<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
		<hr>
			<table border="0" align="center">
				<tr>
					<th>SUPPORT</th>
				</tr>
				<tr>
					<td>
						<p>Send an email to <a href="mailto:support@doeverything.in">support@doeverything.in</a>
					</td>
				</tr>
			</table>
			<p id="footertext"><small>Copyright &copy; doEverything.in | All rights reserved.</small></p>	
		</div>
		
</body>
</html>