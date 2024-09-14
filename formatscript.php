<html>

<head>
    <title>FormatScript</title>
    <meta charset="utf-8">
    <link rel="icon" type="image/x-icon" href="https://www.svgrepo.com/show/533324/code.svg">
    <link rel="stylesheet" href="stylebase5.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Itim&family=Mitr:wght@400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <style>
    iframe{
        border: 0px;
    }

    .my-input{
        width: 50%;
    }
    </style>
</head>
<body>
    <div class="clearfix">
        <div id="nav" class="nav">
            <div class="center-item">
                <h2><a class="warporcleck" href="index.php"><i class="fa fa-code fa-rotate-by"
                            style="font-size:36px"></i> Scripter</a></h2>
            </div>
            <hr size="0.5px">
            <div class="warp-item"><a href="index.php"><i class="fa fa-star"
                        style="font-size:24px"></i>&nbsp;&nbsp;&nbsp;VoxToLua</a></div>
            <div class="warp-item"><a class="active" href=""><i class="fa fa-file-code-o"
                        style="font-size:24px"></i>&nbsp;&nbsp;&nbsp;FormatScript</a>

            </div>
        </div>
        <div class="item">
            <div class="showpage">
                <div class="pagename"><a>FormatScript</a></div>
                <div class="menu" onclick="menu(this)"><i class="fa fa-reorder" style="font-size:34px"></i></div>
            </div>
            <div class="flex-respone">
                <div class="normal-box" style="width:80%;">
                    <a class="topic-box">FormatScript</a>
                    <hr align="left"></br>
                    <div align="left">
                        <select style="border: 1px solid #63A0FF;color:#004FCA;height:70px;width:30%;" onchange="type_selected(this)" id="type-select" name="type-select" class="my-input" aria-label="Default select example" required>
                            <option value="" selected disabled>-- Generate Type --</option>
                            <option value="var">Variable (type)</option>
                            <option value="tab">Table (type)</option>
                        </select>
                        <hr align="left">
                        <input class="my-input" type="text" id="table-name-data" name="table-name" placeholder="Table Name" autocomplete="off"></br>
                        <textarea class='my-input' style='resize:none;width:45%;height:200px;white-space:pre-wrap;overflow-wrap:break-word;' id="key-data" name="key" placeholder="Key...."></textarea>
                        <textarea class='my-input' style='resize:none;width:45%;height:200px;white-space:pre-wrap;overflow-wrap:break-word;' id="value-data" name="value" placeholder="Value...."></textarea>
                        <input class="my-input" type="text" id="format-data" name="table-format" placeholder="Data format" autocomplete="off"></br>
                        <button id="submit-btn" onclick="send_data(this)" class="normal-btn">Generate Script</button>
                        <script>
                        document.getElementById("key-data").style.display = "none";
                        document.getElementById("value-data").style.display = "none";
                        document.getElementById("table-name-data").style.display = "none";
                        document.getElementById("format-data").style.display = "none";
                        document.getElementById("submit-btn").style.display = "none";
                        function type_selected(eleogj) {
                            const value = eleogj.value;
                            if (value == "var") {
                                document.getElementById("key-data").style.display = "";
                                document.getElementById("value-data").style.display = "";
                                document.getElementById("submit-btn").style.display = "";
                                document.getElementById("table-name-data").style.display = "none";
                                document.getElementById("format-data").style.display = "none";
                            } else if (value == "tab") {
                                document.getElementById("key-data").style.display = "";
                                document.getElementById("value-data").style.display = "";
                                document.getElementById("table-name-data").style.display = "";
                                document.getElementById("format-data").style.display = "";
                                document.getElementById("submit-btn").style.display = "";
                            }
                        }
                        </script>
                    </div>
                </div>
                </br></br></br>
                <div id="scriptlua" class="hide" style="width:80%;">
                    <textarea onclick="selectText(this)" class='my-input' style='resize:none;width:100%;height:200px;white-space:pre-wrap;overflow-wrap:break-word;border:none;' id="data-script" readonly></textarea>
                </div>
            </div>
        </div>
    </div>
    <script src="myscript3.js"></script>
    <script>
    function selectText(ele) {
        ele.select();
        document.execCommand("copy");
    }
    </script>
    <script type="text/javascript">
        function send_data(form){
            document.getElementById("scriptlua").className = "hide";
            loader("submit-btn", "loading-page", "status_noti", "status_text", "Wait", "load");
            var form_data = new FormData();
            form_data.append("type-select", document.getElementById('type-select').value);
            form_data.append("table-name", document.getElementById('table-name-data').value);
            form_data.append("key", document.getElementById('key-data').value);
            form_data.append("value", document.getElementById('value-data').value);
            form_data.append("table-format", document.getElementById('format-data').value);
            $.ajax({
                url: 'generatescript.php',
                data: form_data,
                datatype: 'json',
                processData: false,
                contentType: false,
                type: 'POST',
                success: function(data) {
                    console.log(data);
                    let result = data.indexOf("9001");
                    if (result != "-1") {
                        document.getElementById("scriptlua").className = "normal-box";
                        document.getElementById("data-script").innerHTML = data.replace('9001\n','');
                        loader("submit-btn", "loading-page", "status_noti", "status_text", "Success", "success");
                        window.scrollTo(0, document.body.scrollHeight);
                    } else {
                        let result_info = data.indexOf("9002");
                        if (result_info != "-1") {
                            loader("submit-btn", "loading-page", "status_noti", "status_text", "Error", "warn");
                        } else {
                            loader("submit-btn", "loading-page", "status_noti", "status_text", "Error process", "warn");
                        }
                    }
                },
                error: function(data) {
                    loader("submit-btn", "loading-page", "status_noti", "status_text", "Server down", "warn");
                }
            });
        }
    </script>
</body>

</html>