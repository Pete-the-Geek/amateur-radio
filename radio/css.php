<?php
	if ($darkmodeSetting){
			$background = 'black';
			$foreground = 'white';
			$link_colour = 'turquoise';
		} else {
			$background = 'white';
			$foreground = 'black';
			$link_colour = 'blue';
		};
?>
html 
{
	height: 100%;
}
body 
{
	background-color: <?php print $background;  ?>;
	color: <?php print $foreground;  ?>;
	height: 100%;
}
a, abbr
{
	color: <?php print $link_colour;  ?>;
}
.Table
{
	display: table;
}
table.freezeTop
{
	border-color: <?php print $foreground; ?>
	text-align: left;
	position: relative;
}

th.freezeTop 
{
	background: <?php print "#888;"  ?>;
	position: sticky;
	top: 0;
}

}
.Title
{
	display: table-caption;
	text-align: center;
	font-weight: bold;
	font-size: larger;
}
.Heading
{
	display: table-row;
	font-weight: bold;
	text-align: center;
}
.Row
{
	display: table-row;
}
.TopRow
{
	width: 100%;
	overflow:auto;
}
.Cell
{
	display: table-cell;
	padding: 0px;
	white-space: nowrap;
}
.heading
{
	display: table-cell;
	padding: 0px;
	white-space: nowrap;
	border-left: solid black 1px;
}
.myReadOnly{
	background-color : #ccc;
	border-style: hidden;
}
.myHiddenReadOnly{
	background-color: white;
	background-color: white;
	border-style: hidden;
	color: white;
	outline: none;
	outline-color: white;
}
.myHiddenReadOnly::selection{
	background-color: white;
	background-color: white;
	border-style: hidden;
	color: white;
}
form
{
	padding: 0 5px 0 5px;
}
img {
	display: block; 
}
.mapDiv {
	cursor: grab;
	overflow: auto;
	height: 78vh;
	width: 99vw;
	overflow:auto;
	border: solid black 1px;
}
.button {
	width: 100%;
}

.nobutton {
	border:none;
	background:none;
	padding:0;
	text-align:left; 
	color: <?php print $foreground; ?>;
	text-decoration: underline;
	cursor: pointer;
}

/* Tooltip container */
.tooltip {
  position: relative;
  display: inline-block;
  border-bottom: 1px dotted black; /* If you want dots under the hoverable text */
}

/* Tooltip text */
.tooltip .tooltiptext {
  visibility: hidden;
  width: 120px;
  background-color: #555;
  color: #fff;
  text-align: center;
  padding: 5px 0;
  border-radius: 6px;

  /* Position the tooltip text */
  position: absolute;
  z-index: 1;
  bottom: 125%;
  left: 50%;
  margin-left: -60px;

  /* Fade in tooltip */
  opacity: 0;
  transition: opacity 0.3s;
}

/* Tooltip arrow */
.tooltip .tooltiptext::after {
  content: "";
  position: absolute;
  top: 100%;
  left: 50%;
  margin-left: -5px;
  border-width: 5px;
  border-style: solid;
  border-color: #555 transparent transparent transparent;
}

/* Show the tooltip text when you mouse over the tooltip container */
.tooltip:hover .tooltiptext {
  visibility: visible;
  opacity: 1;
}

th {
	padding: 2px;
}
table.gray th {
	background-color: #888;
}

}

.imageBox {
  position: relative;
  float: left;
}

.imageBox .hoverImg {
  position: relative;
  left: 0;
  top: 0;
  display: none;
}

.imageBox:hover .hoverImg .imageBox:active .imageBox:focus{
  display: block;
}

.tableFixHead {
		overflow-y: auto; /* make the table scrollable if height is more than 200 px  */
		max-height: 600px; /* gives an initial height of 200px to the table */
		border: solid grey 1px;
		width: 99%;
	}
.tableFixHead thead {
		position: sticky; /* make the table heads sticky */
		top: 0px; /* table head will be placed from the top of the table and sticks to it */
	}
.tableFixHead th {
		position: sticky; /* make the table heads sticky */
	}
.prettyTable table {
	/*	border-collapse: collapse; /* make the table borders collapse to each other */
		width: 100%;
	}
.prettyTable td {
		padding: 1px 1px; */
		border: 1px solid #ccc;
	}
.prettyTable th {
		padding: 5px 2px;
		border: 1px solid #ccc;
		background: #333;
	}
span {
		text-decoration: underline;
	}
.hover-container {
	position: relative;
}
.hover-content {
	position: absolute;
	top: 10px;
	left: 10px;
	display: none;
	margin: 5px;
	z-index:1000; 
	background-color: <?php print $css_body; ?>;
	border: double 4px <?php print $css_color; ?>;
}
.hover-container:hover .hover-content {
	display: block;
}
.linecharttooltip {
	background-color: <?php print $css_body; ?>;
	border: double 4px <?php print $css_color; ?>;
	color: <?php print $css_color; ?>;
}
.linecharttooltip table {
	background: white;
	color: black;
	border: 1px solid black;
	border-collapse: collapse;
}
.linecharttooltip th, .linecharttooltip td {
	border: 1px solid black;
	padding: 4px;
}
.linecharttooltip thead th {
	background: grey;
	color: white;
}
.linecharttooltip tbody th {
	background: silver;
}

.clipboard:hover, img.image_link, abbr {
	cursor:pointer;
}

/* Container positioned at the top right */
.menu-container {
  position: fixed; /* Use 'fixed' instead if you want it to scroll with the page */
  top: 20px;
  right: 20px;
  z-index: 1000;
}

/* The Hamburger Button */
.hamburger-btn {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  width: 30px;
  height: 24px;
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 0;
}

/* The three lines of the hamburger */
.hamburger-btn span {
  width: 100%;
  height: 4px;
  background-color: #333; /* Color of the hamburger lines */
  border-radius: 4px;
  transition: all 0.3s ease;
}

/* The dropdown menu (hidden by default) */
.menu-items {
  display: none;
  position: absolute;
  top: 35px;
  right: 0;
  background-color: #ffffff;
  min-width: 160px;
  box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
  border-radius: 6px;
  overflow: hidden;
}

/* Show the menu when the 'show' class is added via JavaScript */
.menu-items.show {
  display: block;
}

/* Styling the links */
.menu-items a {
  color: black;
  padding: 12px 16px;
  text-decoration: none;
  display: block;
  font-family: sans-serif;
  font-size: 14px;
}

/* Hover effect for links */
.menu-items a:hover {
  background-color: #f1f1f1;
}

/* Unique container for the bottom right */
.solar-container {
  position: fixed; 
  bottom: 20px; 
  right: 20px;
  z-index: 1000;
}

/* Unique pop-up container that opens upwards */
.solar-content {
  display: none;
  position: absolute;
  bottom: 35px; /* Positions it above the bottom hamburger */
  right: 0;
  background-color: #ffffff;
  box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
  border-radius: 2px;
  overflow: hidden;
  padding: 5px; 
}

/* Show the pop-up when the 'show' class is added via JavaScript */
.solar-content.show {
  display: block;
}

/* Ensure the solar image scales correctly */
.solar-content img {
  display: block;
  max-height: 80vh; 
  /* Prevents the image from overflowing the screen on smaller devices */
  max-width: 90vw; 
  height: 80vh; 
  /* Optional: keeps the text somewhat sharp when scaled up */
  image-rendering: crisp-edges; 
}

/* Optional: Animate this specific hamburger into an 'X' when open */
.hamburger-btn.solar-open span:nth-child(1) {
  transform: translateY(10px) rotate(45deg);
}
.hamburger-btn.solar-open span:nth-child(2) {
  opacity: 0;
}
.hamburger-btn.solar-open span:nth-child(3) {
  transform: translateY(-10px) rotate(-45deg);
}

.hamburger-btn.open span:nth-child(1) {
  transform: translateY(10px) rotate(45deg);
}
.hamburger-btn.open span:nth-child(2) {
  opacity: 0;
}
.hamburger-btn.open span:nth-child(3) {
  transform: translateY(-10px) rotate(-45deg);
}

@media (pointer: coarse), (hover: none) {
      [title] {
        position: relative;
        display: inline-flex;
        justify-content: left;
      }
      [title]:focus::after {
        content: attr(title);
        position: absolute;
        top: 90%;
        color: <?php print $foreground?>;
        background-color: <?php print $background?>;
        border: 1px solid;
        min-width: 400px;
        padding: 3px;
        z-index: 100;
        white-space: pre;
      }
    }
 
@media print {
	body 
	{
		background-color: white;
		color: black;
	}
	a 
	{
		color: blue;
	}
	table
	{
		border-color: black
	}
	th, td, .prettyTable th, .prettyTable th {
		background: white;
		border: 1px solid black;
	}
	
	.hidemeprint
	{
		display: none;
	}
	
	.tableFixHead 
	{
		height: auto;
		max-height: auto;
		width: 100%;
		overflow-y: visible;
	}
	.nobutton {
		color: black;
	}
	.printWhite {
		background: -webkit-gradient(linear, left top, right top, color-stop(100%, white), color-stop(100%, white)) !important;
		background: -moz-linear-gradient(left center, white 100%, white 100%) !important;
		background: -o-linear-gradient(left, white 100%, white 100%) !important;
		background: linear-gradient(to right, white 100%, white 100%)) !important;
	}
}


	* {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
}

@media only print {
	.mapDiv {
		width: auto;
		height: auto;
		overflow: visible;
	}
}
