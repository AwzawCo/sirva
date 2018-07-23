// var casper = require('casper').create({
//     verbose: true,
//     logLevel: "debug"
// }); //for debugging...it prints a lot
var casper = require('casper').create(); 
var mouse = require("mouse").create(casper);
var fs = require('fs');
var utils = require('utils');
var x = require("casper").selectXPath;
var helper = require('./4m77.js');

var currentTime = new Date();
var submittedQuotes = [];
var iterator = -1; //iterator for the bids on the page
var tab; //this needs to be global
var data=null;

var _DATADIR_ = "/var/data/sirva/SubmittedBids/";

casper.on("remote.message", function (msg) {
    console.log(msg);
});

console.log("Start Script.");

function repeat(){
	console.log("\n");
	//Increment the iterator at the start of the function because of concurency issues
	iterator++;
	console.log("attempting to click bid #"+iterator);
	casper.then(function(){
		//this function will return false if there are no more bids to check.
		if(!this.evaluate(clickBid, iterator)){
			console.log("\n\nFinished scraping all the bids");
			return casper.then(finishUp);
		}
		console.log("clicked bid #"+iterator);
		fs.write(_DATADIR_+"bid-"+iterator+".html","<html><body>","w");
		// fs.write(_DATADIR_+helper.MD5(data)+".html","<html><body>","w");
		tab =1;
	}).then(openNextTab).waitFor(getFormData,writeToFile).then(checkForTabs);
}

function openNextTab(){
	//console.log("next tab is "+tab);
	if(tab == 1){
		return true;
	}
	//console.log("Inside openNextTab for tab: "+tab);
	this.evaluate(function(tab){
		var form = document.getElementsByName("clientForm")[0];
		var ix = document.createElement("input");
		var iy = document.createElement("input");
		ix.name = "property"+tab+"Tab.x";
		iy.name = "property"+tab+"Tab.y";
		ix.value = 64;
		iy.value = 16;
		form.appendChild(ix);
	 	form.appendChild(iy);
	 	form.submit();
	},tab);

}

function getFormData(){
	//console.log("inside getFormData");
	data = null;
	return data = this.evaluate(function(formID){
		var form = document.getElementsByName("clientForm")[0];
		form.id = formID;
		var resNumber = form.parentElement.getElementsByTagName("table")[0].rows[1].cells[1].childNodes[0].innerHTML;
		var specialRequests = form.parentElement.getElementsByTagName("table")[1].rows[1].cells[2].innerHTML;
		var bids = document.getElementsByName("bidRequestID");
		var bidID = bids[bids.length-1].value;
		var clientID = -1;
		for(var i=0;i<bids.length-1;i++){
			if(bids[i].value == bidID){
				tr = bids[i].parentElement;
				inputs = tr.getElementsByTagName("input");
				for (var i = 0; i < inputs.length; i++) {
					if(inputs[i].name == "clientID"){
						clientID = inputs[i].value;
						break;
					}
				}
				break;
			}
		}
		return form.outerHTML+"<a id=\"reservationNumber\">"+resNumber+"</a><a id=\"specialRequests\">"+specialRequests+"</a><a id=\"clientID\">"+clientID+"</a>";
	},tab);
}

function checkForTabs(){
	tab++;
	//console.log("tab is at "+tab)
	//Check for more forms attached to this one
	var tabCheck = this.evaluate(function(tab){
		return document.getElementsByName("property"+tab+"Tab")[0] != null;
	},tab) 
	if(tabCheck){
		console.log("Bid #"+iterator+" has another PropertyTab: "+tab);
		casper.then(openNextTab).waitFor(getFormData,writeToFile).then(checkForTabs);
	}
	else{
		console.log("Bid #"+iterator+" has no more property tabs");
		fs.write(_DATADIR_+"bid-"+iterator+".html","</body></html>","a");
		casper.then(repeat);
	}
}

function writeToFile(){
	return fs.write(_DATADIR_+"bid-"+iterator+".html",data,"a");
}

function clickSubmittedBids(){
	var form = document.getElementsByTagName("form")[0];
	var ix = document.createElement("input");
	var iy = document.createElement("input");
	ix.name = "incactiveHousingRequests.x";
	iy.name = "incactiveHousingRequests.y";
	ix.value = 73;
	iy.value = 13;
 	form.appendChild(ix);
 	form.appendChild(iy);
 	form.submit();
}

function clickBid(bidID){
	var tables = document.getElementsByTagName("table");
	var table = tables[2];
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
	var files = fs.list(_DATADIR_);
	for (var i = 0; i < files.length; i++) {
		var file = _DATADIR_+files[i];
		if(fs.isFile(file)){
			var fname = helper.MD5(fs.read(file));
			fs.move(file, _DATADIR_+fname+".html");
		}
	}
	console.log("Finished renaming all the files");
	casper.exit();
}

function getScreenshot(){
	return casper.capture("property.png");
}


casper.start('http://sirvahousing.com/vendors/',function(){
	console.log("Starting CasperJS");
	this.evaluate(function(){
		document.getElementsByName("username")[0].value = "*****";
		document.getElementsByName("password")[0].value = "*****";
		var login = document.getElementsByName("login")[0];
		login.click();
	})

    console.log("Logged in");
})


casper.then(function(){
	this.evaluate(clickSubmittedBids);
	console.log("clicking submittedQuotes");
	return; 
});


casper.then(function(){
	console.log("changing month");
	//Change the month, it's 1-indexed *shudder*
	this.evaluate(function(){
		//Uncomment the next line to access older months.
		//document.getElementsByName("sfMonth")[0].selectedIndex=1;
		document.getElementsByName("applyFilter")[0].click();
	});
});

casper.then(repeat);

casper.run(function(){
	casper.exit();
});
