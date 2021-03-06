<?php session_start();

require_once ("./cfg/config.php");
require_once (ROOT . "/libs.php");

$user = auth () or die ();


$languages = getLanguages ();
$problems = getProblems ();



$lang = @$_POST['selected-language'];
$problem = @$_POST['selected-problem'];
$source = @$_POST['source-code'];
$username = $user->username;

# save recent source-code task and more
$history = (object)array();
$history->source    = $source;
$history->lang      = $lang;
$history->problem   = $problem;
$_SESSION['history'] = $history;


if (! isset ($_POST['selected-language'], $_POST['selected-problem'], $_POST['source-code']))
  header("Location: /");

# language check
if (!isset($languages->$lang))
    header("Location: /?e=lang");
$langInfo = (object)$languages->$lang;

# problem check
if (!isset($problems->$problem))
    header("Location: /?e=problem");
$problemInfo = (object)$problems->$problem;


# preparing paths
$location = join_paths (DATA_ROOT, $user->nameuser, $problem);
mkdirs ($location);
$attemptNo = getNextAttempt ($location);
$sourceFilename = "main.$langInfo->extension";




# jobs stuff
$jobID = sprintf("%s_%s_%s", $username, microtime(true), rand(1, 10*1000));
$jobDir = JOBS_ROOT . "/" . $jobID;
mkdirs ($jobDir, 0777);



ob_end_flush();
ob_start();
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>TGH - zpracování</title>

    <!-- Bootstrap -->
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/styles/default.css" rel="stylesheet" >
    <link href="/css/main.css" rel="stylesheet">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>
  <body>
    <nav class="navbar navbar-default">
      <div class="container-fluid">
        <!-- Brand and toggle get grouped for better mobile display -->
        <div class="navbar-header">
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
            <span class="sr-only">Toggle nav</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="/">TGH</a>
        </div>

        <!-- Collect the nav links, forms, and other content for toggling -->
        <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
          <ul class="nav navbar-nav">
          </ul>
          <ul class="nav navbar-nav navbar-right">
            <li><a href="/logout"><?php showLogout ($user); ?></a></li>
          </ul>
        </div><!-- /.navbar-collapse -->
      </div><!-- /.container-fluid -->
    </nav>

    <div class="jumbotron">
      <div class="container" id="main-cont">
        <h1><a href='/?h' title="Upravit zdrojový kód" class="btn btn-default btn-lg">
              <span class="glyphicon glyphicon-chevron-left" aria-hidden="true">
            </a>
            TGH <small data-prefix=" úloha " class="problem-name"><?php echo $problemInfo->id; ?></small>
        </h1>


        <div class="well" id="processing">Probíhá zpracování...
          <div class="progress">
            <div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
              <span class="sr-only">running</span>
            </div>
          </div>
        </div>

        <!-- <pre id="code"><code class="<?php echo $langInfo->id; ?>"><?php echo $source; ?></code></pre> -->


        <div class="alert alert-success" role="alert" id="output-holder" style="display: block;">
          <strong id="output-header"></strong>
          <pre id="output"><code class="nohighlight"><?php 
              // ob_implicit_flush(1);
              // ob_start();
              ob_flush();
              flush();
              
              # upon copy deamon will process files
              $config = copyJobFiles($jobDir, $problemInfo, $langInfo, $user, $source, $attemptNo);
              $result = waitForResult ($config->config, $config->result);

              # output fix
              if (!isset($result->outputs))
                $result->outputs = array();
              
              if (!isset($result->suffix))
                $result->suffix = 'E';

              # post process
              $resultDir  = join_paths ($location, formatLocation ($username, $problem, $attemptNo) . '_' . $result->suffix);
              mkdirs ($resultDir);


              copyResultFiles ($jobDir, $resultDir, $sourceFilename, $result->result);
              cleanJobFiles ($jobDir);

              print ($result->result);
              print ("<span id='exit-code' style='display: none;'>$result->exit</span>");
           ?></code></pre>
         </div>


          <div class="btn-group" role="group" aria-label="..." id="output-download">
            <?php
            $i = 0;
            foreach ($result->outputs as $output) {
                $serverpath = join_paths ($resultDir, $output->path);
                $wwwpath = str_replace (ROOT, '', $serverpath);
                $cls = $output->exit == '0' ? 'success' : 'danger';
                printf ("<a href='%s' class='btn btn-%s'>výstup sady %02d <br />%s</a>", $wwwpath, $cls, ++$i, getFileSizeString($serverpath));
            }
            ?>
          </div>



      </div>
    </div>


    <footer class="footer">
      <div class="container text-muted">
        <div class="col-md-6">Připomínky zasílejte na jan.hybs (at) tul.cz</div>
        <div class="text-right col-md-6">Veškerý provoz je <strong>monitorován</strong></div>
      </div>
    </footer>


    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script type="text/javascript" src="/js/jquery-2.1.3.min.js"></script>
    <script type="text/javascript" src="/js/bootstrap.min.js"></script>
    <script type="text/javascript" src="/js/highlight.pack.js"></script>
    <script type="text/javascript">hljs.initHighlighting()</script>
    <script type="text/javascript" src="/js/res.js"></script>

  </body>
</html>
