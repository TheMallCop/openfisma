<!-- Sets the doctype from the bootstrap file -->
<?= $this->doctype() ?>

<html>
<head>

<!-- Sets the page title -->
<?
$this->headTitle()->setSeparator(' - ');
$this->headTitle()->prepend('OpenFISMA');
echo $this->headTitle();
?>

<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
<script LANGUAGE="JavaScript" type="text/javascript" src="/javascripts/jquery/jquery.js"></script>
<script LANGUAGE="JavaScript" type="text/javascript" src="/javascripts/jquery/jquery.ui.js"></script>
<script LANGUAGE="JavaScript" type="text/javascript" src="/javascripts/ajax.js"></script>
<link rel="icon"
      type="image/ico"
      href="images/favicon.ico" />


<!--[If lte IE 6]>
<style type="text/css" >
@import url("/stylesheets/ie.css");
</style>
<![endif]-->

<style type="text/css">
<!--
@import url("/stylesheets/main.css");
@import url("/stylesheets/datepicker.css");
@import url("/stylesheets/jquery-ui-themeroller.css");
-->
</style>

</head>
<body>

<div id='container'>

<div id='top' >
        <?php echo $this->layout()->header; ?>
</div><!--top-->


<div id="content">

<div id='detail'>
        <?php echo $this->layout()->CONTENT; ?>
</div><!--detail-->

<div id='bottom'>
        <table width="100%">
        <tr><td colspan=2><hr style="color: #44637A;" size="1"></td></tr>
        <tr> <td>If you find bugs or wish to provide feedback, please <a href="mailto:mark.haase@ed.gov?Subject=OVMS%20Feedback%2FBugs">contact us</a>.</td>
             <td align="right"> <i>Powered by <a href="http://www.openfisma.org">OpenFISMA</a></i> </td>
        </tr>
        </table>
</div><!--bottom-->

</div><!--content-->

</div><!--container-->

</body>
</html>
