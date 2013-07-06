<?php
#Start session
if(!isset($_SESSION)) session_start();

if(!defined('Sprinklers')) {

    #Tell main we are calling it
    define('Sprinklers', TRUE);

    #Required files
    require_once "../main.php";
    
    header("Content-type: application/x-javascript");
}

#Kick if not authenticated
if (!is_auth()) {header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found', true, 404);exit();}

#Echo token so browser can cache it for automatic logins
if (isset($_SESSION['sendtoken']) && $_SESSION['sendtoken']) { echo "localStorage.setItem('token', '".$_SESSION['token']."');\n"; $_SESSION['sendtoken'] = false; }
?>
//Set AJAX timeout
$.ajaxSetup({
    timeout: 5000
});

//Handle timeout
$(document).ajaxError(function(x,t,m) {
    if(t.statusText==="timeout") {
        if (m.url == "index.php?action=get_weather") {
            $("#weather").html("")
        } else {
            showerror("Connection timed-out. Please try again.")
        }
    }
});

//After main page is processed, hide loading message and change to the page
$(document).one("pageinit","#sprinklers", function(){
    $.mobile.hidePageLoadingMsg();
    var date = new Date();
    var y = date.getFullYear();
    var m = String(date.getMonth()+1);
    if (m.length == 1) m = "0"+m;
    var d = String(date.getDate());
    if (d.length == 1) d = "0"+d;
    $("#preview_date").val(y+"-"+m+"-"+d);
    $.mobile.changePage($("#sprinklers"),{transition:"none"});
    var curr = $("#commit").html();
    if (curr !== undefined) {
        $.getJSON("https://api.github.com/repos/salbahra/OpenSprinkler-Controller/git/refs/heads/master").done(function(data){
            var newest = data.object.sha;
            if (newest != curr) $("#showupdate").fadeIn(500).delay(2000).fadeOut(500);
        })
    }
});

//This bind intercepts most links to remove the 300ms delay iOS adds
$(document).on('pageinit', function (e, data) {
    var newpage = e.target.id;

    if (newpage == "sprinklers" || newpage == "status" || newpage == "manual" || newpage == "logs" || newpage == "programs" || newpage == "settings" || newpage == "os-settings" || newpage == "os-stations" || newpage == "raindelay") {
        var currpage = $(e.target);

        currpage.find("a[href='#"+currpage.attr('id')+"-settings']").bind('vclick', function (e) {
            e.preventDefault(); e.stopImmediatePropagation();
            highlight(this);
            $(".ui-page-active [id$=settings]").panel("open");
        });
        currpage.find("a[onclick]").bind('vclick', function (e) {
            e.preventDefault(); e.stopImmediatePropagation();
            var func = $(this).attr("onclick").split(";")[0];
            highlight(this);
            eval(func);
        });
    }
});

$(window).bind("resize",function(e){
    var content_height = $.mobile.activePage.children('[data-role="content"]').height(),
        header_height  = $.mobile.activePage.children('[data-role="header"]').height(),
        window_height  = $(this).height(),
        total          = content_height + header_height;

    if (window_height > total) total = window_height;
    $.mobile.activePage.css('min-height', total);
    e.stopImmediatePropagation();
})

$("#preview_date").change(function(){
    var id = $(".ui-page-active").attr("id");
    if (id == "preview") get_preview()
});

//Bind changes to the flip switches
$("select[data-role='slider']").change(function(){
    var slide = $(this);
    var type = this.name;
    var pageid = slide.closest(".ui-page-active").attr("id");
    //Find out what the switch was changed to
    var changedTo = slide.val();
    if(window.sliders[type]!==changedTo){
        window.sliders[type] = changedTo;
        if (changedTo=="on") {
            //If chanegd to on
            if (type === "autologin") {
                if (localStorage.getItem("token") !== null) return;
                $("#login form").attr("action","javascript:grab_token('"+pageid+"')");
                $("#login .ui-checkbox").hide();
                $.mobile.changePage($("#login"));
            }
            if (type === "en") {
                $.get("index.php","action=en_on",function(result){
                    //If switch failed then change the switch back and show error
                    if (result == 0) {
                        comm_error()
                        $("#en").val("off").slider("refresh")
                    }
                });
            }
            if (type === "auto_mm") {
                $.get("index.php","action=auto_mm_on",function(result){
                    //If switch failed then change the switch back and show error
                    if (result == 0) {
                        showerror("Auto disable of manual mode was not changed. Check config.php permissions and try again.")
                        $("#auto_mm").val("off").slider("refresh")
                    }
                });
            }
            if (type === "mm" || type === "mmm") {
                $.get("index.php","action=mm_on",function(result){
                    if (result == 0) {
                        comm_error()
                        $("#mm,#mmm").val("off").slider("refresh")
                    }
                });
                $("#mm,#mmm").val("on").slider("refresh");
            }
        } else {
            //If chanegd to off
            if (type === "autologin") {
                localStorage.removeItem(typeToKey(type));
            }
            if (type === "en") {
                $.get("index.php","action=en_off",function(result){
                    if (result == 0) {
                        comm_error()
                        $("#en").val("on").slider("refresh")
                    }
                });
            }
            if (type === "auto_mm") {
                $.get("index.php","action=auto_mm_off",function(result){
                    if (result == 0) {
                        showerror("Auto disable of manual mode was not changed. Check config.php permissions and try again.")
                        $("#auto_mm").val("on").slider("refresh")
                    }
                });
            }
            if (type === "mm" || type === "mmm") {
                $.get("index.php","action=mm_off",function(result){
                    if (result == 0) {
                        comm_error()
                        $("#mm,#mmm").val("on").slider("refresh")
                    }
                });
                //If switched to off, unhighlight all of the manual zones highlighted in green since all will be disabled automatically
                $("#manual a.green").removeClass("green");
                $("#mm,#mmm").val("off").slider("refresh");
            }
        }
    }
});

function comm_error() {
    showerror("Error communicating with OpenSprinkler. Please check your password is correct.")
}

$(document).on("pageshow",function(e,data){
    var newpage = e.target.id;

    if (newpage == "sprinklers") {
        //Automatically update sliders on page load in settings panel
        check_auto($("#"+newpage+" select[data-role='slider']"));
    } else if (newpage == "preview") {
        get_preview();
    }
});

$(document).on("pagebeforeshow",function(e,data){
    var newpage = e.target.id;

    $.mobile.silentScroll(0);

    if (newpage == "sprinklers") {
        update_weather();
        //Check if a program is running
        $.get("index.php","action=current_status",function(data){
            var footer = $("#footer-running")
            if (data === "") {
                footer.css("opacity",0);
                return;
            }
            data = JSON.parse(data);
            var minutes = parseInt( data.seconds / 60 ) % 60;
            var seconds = data.seconds % 60;
            var line = data.program + " is running on station <span class='nobr'>" + data.station + "</span> <span id='countdown' class='nobr'>(" + (minutes < 10 ? "0" + minutes : minutes) + ":" + (seconds  < 10 ? "0" + seconds : seconds) + " remaining)</span>";
            if (data.seconds != 0) update_timer(data.seconds);
            $("#running-text").html(line);
            $("#running-icon").css("top",footer.height()/2 - 5.5 + "px")        
            footer.animate({"opacity": "0.88"})
        })
    } else {
        clearInterval(window.interval_id);
        var title = document.title;
        document.title = "OpenSprinkler: "+title;
    }

    if (newpage == "raindelay") {
        $.get("index.php","action=get_autodelay",function(data){
            data = JSON.parse(data)
            if (data["auto_delay"]) {
                $("#auto_delay").val("on").slider("refresh")
            }
            $("#auto_delay_duration").val(data["auto_delay_duration"]).slider("refresh");
        })
    }
})

function update_timer(total) {
    window.interval_id = setInterval(function(){
        if (total <= 0) {
            clearInterval(interval_id);
            $("#footer-running").css("opacity",0).html("");
        }
        else
            --total;
            var minutes = parseInt( total / 60 ) % 60;
            var seconds = total % 60;
            $("#countdown").text("(" + (minutes < 10 ? "0" + minutes : minutes) + ":" + (seconds < 10 ? "0" + seconds : seconds) + " remaining)");
    },1000)
}

function check_auto(sliders){
    if (typeof(window.sliders) !== "object") window.sliders = [];
    sliders.each(function(i){
        var type = this.name;
        var item = typeToKey(type);
        if (!item) return;
        if (localStorage.getItem(item) != null) {
            window.sliders[type] = "on";
            $(this).val("on").slider("refresh");
        } else {
            window.sliders[type] = "off";
            $(this).val("off").slider("refresh");
        }
    })
}

function typeToKey(type) {
    if (type == "autologin") {
        return "token";
    } else {
        return false;
    }
}

function highlight(button) {
    $(button).addClass("ui-btn-active").delay(150).queue(function(next){
        $(this).removeClass("ui-btn-active");
        next();
    });
}

function grab_token(pageid){
    $.mobile.showPageLoadingMsg();
    var parameters = "action=gettoken&username=" + $('#username').val() + "&password=" + $('#password').val() + "&remember=true";
    $("#username, #password").val('');
    $.post("index.php",parameters,function(reply){
        $.mobile.hidePageLoadingMsg();
        if (reply == 0) {
            $.mobile.changePage($("#"+pageid));
            showerror("Invalid Login");
        } else if (reply === "") {
            $("#"+pageid+"-autologin").val("off").slider("refresh");
            window.sliders["autologin"] = "off";
            $.mobile.changePage($("#"+pageid));
        } else {
            localStorage.setItem('token',reply);
            $.mobile.changePage($("#"+pageid));
        }
        $("#login .ui-checkbox").show()
        $("#login form").attr("action","javascript:dologin()");
    }, "text");
}

function update_weather() {
    $.get("index.php","action=get_weather",function(result){
        var weather = JSON.parse(result);
        if (weather["code"] == null) {
            $("#weather").html("");
            return
        }
        $("#weather").html("<p title='"+weather["text"]+"' class='wicon cond"+weather["code"]+"'></p><span>"+weather["temp"]+"</span><br><span class='location'>"+weather["location"]+"</span>");
    })
}

function logout(){
    areYouSure("Are you sure you want to logout?", "", function() {
        $.mobile.changePage($("#login"));
        $.get("index.php", "action=logout",function(){
            localStorage.removeItem('token');
            $("body div[data-role='page']:not('.ui-page-active')").remove();
            $('.ui-page-active').one("pagehide",function(){
                $(this).remove();
            })
        });
    },gohome);
}

function gohome() {
    $.mobile.changePage($('#sprinklers'), {reverse: true});
}

function show_settings() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=make_settings_list",function(items){
        var list = $("#os-settings-list");
        list.html(items).trigger("create");
        if (list.hasClass("ui-listview")) list.listview("refresh");
        $.mobile.hidePageLoadingMsg();
        $.mobile.changePage($("#os-settings"));
    })    
}

function show_stations() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=make_stations_list",function(items){
        var list = $("#os-stations-list");
        list.html(items).trigger("create");
        if (list.hasClass("ui-listview")) list.listview("refresh");
        $.mobile.hidePageLoadingMsg();
        $.mobile.changePage($("#os-stations"));
    })    
}

function get_status() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=make_list_status",function(items){
        var list = $("#status_list");
        list.html(items);
        if (list.hasClass("ui-listview")) list.listview("refresh");
        $.mobile.hidePageLoadingMsg();
        $.mobile.changePage($("#status"));
    })
}

function get_logs() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=make_list_logs",function(items){
        var list = $("#logs_list");
        list.html(items).trigger("create");
        $.mobile.hidePageLoadingMsg();
        $.mobile.changePage($("#logs"));
    })
}

function get_manual() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=make_list_manual",function(items){
        var list = $("#mm_list");
        list.html(items);
        if (list.hasClass("ui-listview")) list.listview("refresh");
        $.mobile.hidePageLoadingMsg();
        $.mobile.changePage($("#manual"));
    })
}

function get_runonce() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=make_runonce",function(items){
        var list = $("#runonce_list");
        list.html(items);
        list.trigger("create");
        $.mobile.hidePageLoadingMsg();
        $.mobile.changePage($("#runonce"));
    })
}

function get_preview() {
    $("#timeline").html("");
    $("#timeline-navigation").hide()
    var date = $("#preview_date").val();
    if (date === "") return;
    date = date.split("-");
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=get_preview&d="+date[2]+"&m="+date[1]+"&y="+date[0],function(items){
        var empty = true;
        if (items == "") {
            $("#timeline").html("<p align='center'>No stations set to run on this day.</p>")
        } else {
            empty = false
            var data = eval("["+items.substring(0, items.length - 1)+"]");
            $.each(data, function(){
                this.start = new Date(date[0],date[1]-1,date[2],0,0,this.start);
                this.end = new Date(date[0],date[1]-1,date[2],0,0,this.end);
            })
            var options = {
                'width':  '100%',
                'editable': false,
                'axisOnTop': true,
                'eventMargin': 10,
                'eventMarginAxis': 0,
                'min': new Date(date[0],date[1]-1,date[2],0),
                'max': new Date(date[0],date[1]-1,date[2],24),
                'selectable': true,
                'showMajorLabels': false,
                'zoomMax': 1000 * 60 * 60 * 24,
                'zoomMin': 1000 * 60 * 60,
                'groupsChangeable': false,
                'showNavigation': false
            };

            window.timeline = new links.Timeline(document.getElementById('timeline'));
            links.events.addListener(timeline, "select", function(){
                var row = undefined;
                var sel = timeline.getSelection();
                if (sel.length) {
                    if (sel[0].row != undefined) {
                        row = sel[0].row;
                    }
                }
                if (row === undefined) return;
                var content = $(".timeline-event-content")[row];
                var pid = parseInt($(content).html().substr(1)) - 1;
                get_programs(pid);
            });
            window.addEventListener("resize",timeline_redraw);
            timeline.draw(data, options);
            if ($(window).width() <= 480) {
                var currRange = timeline.getVisibleChartRange();
                if ((currRange.end.getTime() - currRange.start.getTime()) > 6000000) timeline.setVisibleChartRange(currRange.start,new Date(currRange.start.getTime()+6000000))
            }
            $("#timeline .timeline-groups-text:contains('Master')").addClass("skip-numbering")
            $("#timeline-navigation").show()
        }
        $.mobile.hidePageLoadingMsg();
    })
}

function timeline_redraw() {
    window.timeline.redraw();
}

function changeday(dir) {
    var inputBox = $("#preview_date");
    var date = inputBox.val();
    if (date === "") return;
    date = date.split("-");
    var nDate = new Date(date[0],date[1]-1,date[2]);
    nDate.setDate(nDate.getDate() + dir);
    var m = String(nDate.getMonth()+1);
    if (m.length == 1) m = "0"+m;
    var d = String(nDate.getDate());
    if (d.length == 1) d = "0"+d;
    inputBox.val(nDate.getFullYear() + "-" + m + "-" + d);
    get_preview();
}

function get_programs(pid) {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=make_all_programs",function(items){
        var list = $("#programs_list");
        list.html(items);
        if (typeof pid !== 'undefined') {
            if (pid === false) {
                $.mobile.silentScroll(0)
            } else {
                $("#programs fieldset[data-collapsed='false']").attr("data-collapsed","true");
                $("#program-"+pid).attr("data-collapsed","false")
            }
        }
        $("#programs input[name^='rad_days']").change(function(){
            var progid = $(this).attr('id').split("-")[1], type = $(this).val().split("-")[0], old;
            type = type.split("_")[1];
            if (type == "n") {
                old = "week"
            } else {
                old = "n"
            }
            $("#input_days_"+type+"-"+progid).show()
            $("#input_days_"+old+"-"+progid).hide()
        })

        $("#programs [id^='submit-']").click(function(){
            submit_program($(this).attr("id").split("-")[1]);
        })
        $("#programs [id^='s_checkall-']").click(function(){
            var id = $(this).attr("id").split("-")[1]
            $("[id^='station_'][id$='-"+id+"']").prop("checked",true).checkboxradio("refresh");
        })
        $("#programs [id^='s_uncheckall-']").click(function(){
            var id = $(this).attr("id").split("-")[1]
            $("[id^='station_'][id$='-"+id+"']").prop("checked",false).checkboxradio("refresh");
        })
        $("#programs [id^='delete-']").click(function(){
            delete_program($(this).attr("id").split("-")[1]);
        })
        $.mobile.hidePageLoadingMsg();
        $("#programs").trigger("create");
        $.mobile.changePage($("#programs"));
    })
}

function add_program() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=fresh_program",function(items){
        var list = $("#newprogram");
        list.html(items);
        $("#addprogram input[name^='rad_days']").change(function(){
            var progid = "new", type = $(this).val().split("-")[0], old;
            type = type.split("_")[1];
            if (type == "n") {
                old = "week"
            } else {
                old = "n"
            }
            $("#input_days_"+type+"-"+progid).show()
            $("#input_days_"+old+"-"+progid).hide()
        })
        $("#addprogram [id^='s_checkall-']").click(function(){
            $("[id^='station_'][id$='-new']").prop("checked",true).checkboxradio("refresh");
        })
        $("#addprogram [id^='s_uncheckall-']").click(function(){
            $("[id^='station_'][id$='-new']").prop("checked",false).checkboxradio("refresh");
        })
        $("#addprogram [id^='submit-']").click(function(){
            submit_program("new");
        })
        $.mobile.hidePageLoadingMsg();
        $("#addprogram").trigger("create");
        $.mobile.changePage($("#addprogram"));
    })    
}

function delete_program(id) {
    if(!confirm("Are you sure you want to delete program "+(parseInt(id)+1)+"?")) return false;
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=delete_program&pid="+id,function(result){
        $.mobile.hidePageLoadingMsg();
        if (result == 0) {
            comm_error()
        } else {
            get_programs(false)
        }
    })
}

function submit_program(id) {
    var program = [], days=[0,0]
    program[0] = ($("#en-"+id).is(':checked')) ? 1 : 0

    if($("#days_week-"+id).is(':checked')) {
        for(i=0;i<7;i++) {if($("#d"+i+"-"+id).is(':checked')) {days[0] |= (1<<i); }}
        if($("#days_odd-"+id).is(':checked')) {days[0]|=0x80; days[1]=1;}
        else if($("#days_even-"+id).is(':checked')) {days[0]|=0x80; days[1]=0;}
    } else if($("#days_n-"+id).is(':checked')) {
        days[1]=parseInt($("#every-"+id).val(),10);
        if(!(days[1]>=2&&days[1]<=128)) {showerror("Error: Interval days must be between 2 and 128.");return;}
        days[0]=parseInt($("#starting-"+id).val(),10);
        if(!(days[0]>=0&&days[0]<days[1])) {showerror("Error: Starting in days wrong.");return;}
        days[0]|=0x80;
    }
    program[1] = days[0]
    program[2] = days[1]

    var start = $("#start-"+id).val().split(":")
    program[3] = parseInt(start[0])*60+parseInt(start[1])
    var end = $("#end-"+id).val().split(":")
    program[4] = parseInt(end[0])*60+parseInt(end[1])

    if(!(program[3]<program[4])) {showerror("Error: Start time must be prior to end time.");return;}

    program[5] = parseInt($("#interval-"+id).val())
    program[6] = $("#duration-"+id).val() * 60

    var sel = $("[id^=station_][id$=-"+id+"]")
    var total = sel.length
    var nboards = total / 8


    var stations=[0],station_selected=0,bid, sid;
    for(bid=0;bid<nboards;bid++) {
        stations[bid]=0;
        for(s=0;s<8;s++) {
            sid=bid*8+s;
            if($("#station_"+sid+"-"+id).is(":checked")) {
                stations[bid] |= 1<<s; station_selected=1;
            }
        }
    }
    if(station_selected==0) {showerror("Error: You have not selected any stations.");return;}
    program = JSON.stringify(program.concat(stations))
    $.mobile.showPageLoadingMsg()
    if (id == "new") {
        $.get("index.php","action=update_program&pid=-1&data="+program,function(result){
            $.mobile.hidePageLoadingMsg()
            get_programs()
            if (result == 0) {
                setTimeout(comm_error,400)
            } else {
                setTimeout(function(){showerror("Program added successfully")},400)
            }
        });
    } else {
        $.get("index.php","action=update_program&pid="+id+"&data="+program,function(result){
            $.mobile.hidePageLoadingMsg()
            if (result == 0) {
                comm_error()
            } else {
                showerror("Program has been updated")
            }
        });
    }
}

function submit_settings() {
    var opt = {}, invalid = false;
    $("#os-settings-list").find(":input").each(function(a,b){
        var $item = $(b), id = $item.attr('id'), data = $item.val();
        switch (id) {
            case "o1":
                var tz = data.split(":")
                tz[0] = parseInt(tz[0],10);
                tz[1] = parseInt(tz[1],10);
                tz[1]=(tz[1]/15>>0)/4.0;tz[0]=tz[0]+(tz[0]>=0?tz[1]:-tz[1]);
                data = ((tz[0]+12)*4)>>0
                break;
            case "o16":
            case "o21":
            case "o22":
            case "o25":
                data = $item.is(":checked") ? 1 : 0
                if (!data) return true
                break;
        }
        opt[id] = encodeURIComponent(data)
    })
    if (invalid) return
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=submit_options&options="+JSON.stringify(opt),function(result){
        $.mobile.hidePageLoadingMsg();
        gohome();
        if (result == 0) {
            comm_error()
        } else {
            showerror("Settings have been saved")
        }
    })
}

function submit_stations() {
    var names = {}, invalid = false,v="";bid=0,s=0,m={},masop="";
    $("#os-stations-list").find(":input,p[id^='um_']").each(function(a,b){
        var $item = $(b), id = $item.attr('id'), data = $item.val();
        switch (id) {
            case "edit_station_" + id.slice("edit_station_".length):
                id = "s" + id.split("_")[2]
                if (data.length > 16) {
                    invalid = true
                    $item.focus()
                    showerror("Station name must be 16 characters or less")
                    return false
                }
                names[id] = encodeURIComponent(data)
                return true;
                break;
            case "um_" + id.slice("um_".length):
                v = ($item.is(":checked") || $item.prop("tagName") == "P") ? "1".concat(v) : "0".concat(v);
                s++;
                if (parseInt(s/8) > bid) {
                    m["m"+bid]=parseInt(v,2); bid++; s=0; v="";
                }
                return true;
                break;
        }
    })
    m["m"+bid]=parseInt(v,2);
    if ($("[id^='um_']").length) masop = "&masop="+JSON.stringify(m);
    if (invalid) return
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=submit_stations&names="+JSON.stringify(names)+masop,function(result){
        $.mobile.hidePageLoadingMsg();
        gohome();
        if (result == 0) {
            comm_error()
        } else {
            showerror("Stations have been updated")
        }
    })
}

function submit_runonce() {
    var runonce = []
    $("#runonce").find(":input[data-type='range']").each(function(a,b){
        runonce.push(parseInt($(b).val())*60)
    })
    $.get("index.php","action=runonce&data="+JSON.stringify(runonce),function(result){
        if (result == 0) {
            comm_error()
        } else {
            showerror("Run-once program has been scheduled")
        }
    })
    gohome();
}

function toggle(anchor) {
    if ($("#mm").val() == "off") return;
    var $list = $("#mm_list");
    var $anchor = $(anchor);
    var $listitems = $list.children("li:not(li.ui-li-divider)");
    var $item = $anchor.closest("li:not(li.ui-li-divider)");
    var currPos = $listitems.index($item) + 1;
    var total = $listitems.length;
    if ($anchor.hasClass("green")) {
        $.get("index.php","action=spoff&zone="+currPos,function(result){
            if (result == 0) {
                $anchor.addClass("green");
                comm_error()
            }
        })
        $anchor.removeClass("green");
    } else {
        $.get("index.php","action=spon&zone="+currPos,function(result){
            if (result == 0) {
                $anchor.removeClass("green");
                comm_error()
            }
        })
        $anchor.addClass("green");
    }
}

function raindelay() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=raindelay&delay="+$("#delay").val(),function(result){
        $.mobile.hidePageLoadingMsg();
        gohome();
        if (result == 0) {
            comm_error()
        } else {
            showerror("Rain delay has been successfully set")
        }
    });
}

function auto_raindelay() {
    $.mobile.showPageLoadingMsg();
    var params = {
        "auto_delay": $("#auto_delay").val(),
        "auto_delay_duration": $("#auto_delay_duration").val()
    }
    params = JSON.stringify(params)
    $.get("index.php","action=submit_autodelay&autodelay="+params,function(result){
        $.mobile.hidePageLoadingMsg();
        gohome();
        if (result == 2) {
            showerror("Auto-delay changes were not saved. Check config.php permissions and try again.");
        } else {
            showerror("Auto-delay changes have been saved")
        }
    })
}

function rbt() {
    areYouSure("Are you sure you want to reboot OpenSprinkler?", "", function() {
        $.mobile.showPageLoadingMsg()
        $.get("index.php","action=rbt",function(result){
            $.mobile.hidePageLoadingMsg()
            gohome();
            if (result == 0) {
                comm_error()
            } else {
                showerror("OpenSprinkler is rebooting now")
            }
        });
    },gohome);
}

function rsn() {
    areYouSure("Are you sure you want to stop all stations?", "", function() {
        $.mobile.showPageLoadingMsg()
        $.get("index.php","action=rsn",function(result){
            $.mobile.hidePageLoadingMsg()
            gohome();
            if (result == 0) {
                comm_error()
            } else {
                showerror("All stations have been stopped")
            }
        });
    },gohome);
}

function export_config() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=export_config",function(data){
        $.mobile.hidePageLoadingMsg();
        $("#sprinklers-settings").panel("close")
        if (data === "") {
            comm_error()
        } else {
            localStorage.setItem("backup", data);
            showerror("Backup saved to your device");
        }
    })
}

function import_config() {
    var data = localStorage.getItem("backup");
    if (data === null) {
        showerror("No backup available on this device");
        return;
    }

    areYouSure("Are you sure you want to restore the configuration?", "", function() {
        $.mobile.showPageLoadingMsg();
        $.get("index.php","action=import_config&data="+data,function(reply){
            $.mobile.hidePageLoadingMsg();
            gohome();
            if (reply == 0) {
                comm_error()
            } else {
                showerror("Backup restored to your device");
            }
        })
    },gohome);
}

function areYouSure(text1, text2, callback, callback2) {
    $("#sure .sure-1").text(text1);
    $("#sure .sure-2").text(text2);
    $("#sure .sure-do").unbind("click.sure").on("click.sure", function() {
        callback();
    });
    $("#sure .sure-dont").unbind("click.sure").on("click.sure", function() {
        callback2();
    });
    $.mobile.changePage("#sure");
}