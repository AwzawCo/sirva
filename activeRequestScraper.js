// var casper = require('casper').create({
//     verbose: true,
//     logLevel: "debug"
// }); //for debugging...it prints a lot 
var helper = require('./4m77.js');
var casper = require('casper').create(); 
var mouse = require("mouse").create(casper);
var fs = require('fs');
var utils = require('utils');
var system = require('system');
var x = require("casper").selectXPath;

var log = helper.log;
var _DATADIR_DEV = "./leadForms/";
var _DATADIR_ = "/var/data/sirva/ActiveRequests/"
var submittedQuotes = [];
var iterator = -1; //iterator for the bids on the page
var tab; 
var data=null;  
casper.on("remote.message", function (msg) {
    log(msg);
});

log("\nStart Script.");

function repeat(){
	//Increment the iterator at the start of the function because of concurency issues
	iterator++;
	console.log("attempting to click lead #"+iterator);
	casper.then(function(){
		//this function will return false if there are no more bids to check.
		if(!this.evaluate(clickLead, iterator)){
			console.log("\n\nFinished scraping all the leads");
			return casper.then(finishUp);
		}
		console.log("clicked lead #"+iterator);
	}).then(writeToFile).then(repeat);
}

function writeToFile(){
	data = this.getHTML();
	fs.write(_DATADIR_+helper.MD5(data)+".html",data,"w");
	console.log("Wrote "+_DATADIR_+helper.MD5(data)+".html\n");
}

function clickLead(bidID){
	var tables = document.getElementsByTagName("table");
	var table = tables[0];
	var forms = table.getElementsByTagName("form");
	var form = forms[bidID];
	if(!form){
		return false;
	}
	var ix = document.createElement("input");
	var iy = document.createElement("input");
	ix.name = "openActiveHousing.x";
	iy.name = "openActiveHousing.y";
	ix.value = 46;
	iy.value = 11;
 	form.appendChild(ix);
 	form.appendChild(iy);
 	form.submit();
 	return true;
}

function finishUp(){
	//this will verify that the script actually ran.
	fs.touch(_DATADIR_+"_A_");
	casper.exit();
}

// function getScreenshot(){
// 	return casper.capture("test.png");
// }


casper.start('http://sirvahousing.com/vendors/',function(){
	console.log("Starting CasperJS");
	this.evaluate(function(){
		document.getElementsByName("username")[0].value = "******";
		document.getElementsByName("password")[0].value = "******";
		var login = document.getElementsByName("login")[0];
		login.click();
	})

    console.log("Logged in");
});

casper.then(repeat);

casper.run(function(){
	casper.exit();
});

