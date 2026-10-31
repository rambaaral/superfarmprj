<?php

if(!defined('LOGINIZER_FIREWALL')){
	die('You came through wrong path!');
}

 ?><!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Access Blocked</title>
<style>
body *{
box-sizing:border-box;
}

body{
margin: 0;
line-height: inherit;
background-color:#f6f9fc;
font-family:sans-serif;
box-sizing:border-box;
}

blockquote, dd, dl, figure, h1, h2, h3, h4, h5, h6, hr, p, pre {
margin: 0;
}

.main-wrapper{
display: flex; 
overflow-x: hidden; 
position: relative; 
flex-direction: column; 
width: 100%; 
height: auto; 
min-height: 100vh; 
}

main{
display: flex; 
flex-direction: column; 
justify-content: center; 
align-items: center; 
height: 100%;
}

.main-content-wrap{
display: flex; 
padding-left: 1rem;
padding-right: 1rem; 
padding-top: 2.5rem;
padding-bottom: 2.5rem; 
flex: 1 1 0%; 
justify-content: center; 
align-items: center; 
width: 100%; 
}

.main-content{
display: flex; 
flex-direction: column; 
align-items: center; 
width: 100%; 
max-width: 42rem; 
}

.security-icon{
display: flex;
margin-bottom: 1.5rem;
justify-content: center;
align-items: center;
border-radius: 9999px;
width: 4rem;
height: 4rem;
background-color:#e5484d1a;
}

h1{	
padding-left: 1rem;
padding-right: 1rem; 
padding-top: 0.5rem; 
padding-bottom: 0.75rem; 
font-size: 1.875rem;
line-height: 2.25rem; 
font-weight: 700; 
letter-spacing: -0.025em; 
line-height: 1.25; 
text-align: center;
color:#0A2540;
}

.heading-supporter{
padding-left: 1rem;
padding-right: 1rem; 
padding-top: 0.25rem; 
padding-bottom: 2rem; 
max-width: 36rem; 
font-size: 1rem;
line-height: 1.5rem; 
font-weight: 400; 
line-height: 1.625; 
text-align: center;
color::#425466;
}

.info-block{
padding: 1.5rem; 
border-radius: 0.75rem; 
border:1px solid #E0E6EB;
width: 100%;
background-color:#FFF;
}

.info-block h2{
margin-bottom: 1rem;
font-size: 1.125rem;
line-height: 1.75rem;
font-weight: 700;
color:#0A2540;
}

.info-block-grid{
display: grid;
grid-template-columns: repeat(1, minmax(0, 1fr)); 
column-gap: 1rem; 
row-gap: 1rem; 
font-size: 0.875rem;
line-height: 1.25rem; 
}

.info-block-grid p{
font-weight: 500;
color: #425466;
}

.info-block-grid p:nth-child(even){
color: #0A2540;
}

footer{
padding-left: 1rem;
padding-right: 1rem; 
padding-top: 1.5rem;
padding-bottom: 1.5rem; 
width: 100%; 
text-align: center; 
}

footer p{
font-size: 0.75rem;
line-height: 1rem; 
color:#425466;
}

@media (min-width: 640px) { 
h1{
font-size: 2.25rem;
line-height: 2.5rem; 
}

.heading-supporter{
font-size: 1.125rem;
line-height: 1.75rem;
}

.info-block{
padding: 2rem; 
}

.info-block-grid{
grid-template-columns: auto 1fr;
column-gap: 1.5rem;
font-size: 1rem;
line-height: 1.5rem; 
}

.main-feedback{
display: flex; 
padding-left: 1rem;
padding-right: 1rem; 
padding-top: 2rem;
padding-bottom: 2rem; 
flex-direction: column; 
gap: 1rem; 
justify-content: center; 
align-items: center; 
width: 100%; 
}

footer{
padding-left: 2rem;
padding-right: 2rem;
}
}

</style>
</head>
<body>
	<div class="main-wrapper">
		<main>
			<div class="main-content-wrap">
				<div class="main-content">
					<div class="security-icon">
					<svg xmlns="http://www.w3.org/2000/svg" height="34px" viewBox="0 -960 960 960" width="34px" fill="#e5484d"><path d="M480-80q-139-35-229.5-159.5T160-516v-244l320-120 320 120v244q0 152-90.5 276.5T480-80Zm0-84q97-30 162-118.5T718-480H480v-315l-240 90v207q0 7 2 18h238v316Z"/></svg>
					</div>
					<h1>Your access to this site has been blocked.</h1>
					<p class="heading-supporter">
						This action was taken based on our security policy. (HTTP response code 403)
					</p>
					<div class="info-block">
						<h2>Block Reason</h2>
						<div class="info-block-grid">
							<p>IP Address</p>
							<p><?php echo !empty($loginizer['current_ip']) ? htmlspecialchars($loginizer['current_ip']) : ''; ?></p>
							<p>Type</p>
							<p>Policy Violation</p>
							<p>Details</p>
							<p>Country Blocked</p>
							<p>Time</p>
							<p><?php echo gmdate('M d Y H:i:s e');?></p>
							<p>Destination</p>
							<p><?php echo (!empty($_SERVER['HTTP_SCHEMA']) ? htmlspecialchars($_SERVER['HTTP_SCHEMA']) : '').htmlspecialchars($_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);?></p>
						</div>
					</div>
					<div class="main-feedback">
						<span class="truncate">If you think you have been blocked in error, contact the site admin.</span>
					</div>
				</div>
			</div>
		</main>
		<footer>
			<p>Powered by: Loginizer</p>
		</footer>
	</div>

</body>
</html>