<html>

<head>
    <title>VoxToLua</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="stylebase5.css">
    <link rel="icon" type="image/x-icon" href="https://www.svgrepo.com/show/533324/code.svg">
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

    input[type="file"]::file-selector-button{
        background-color: white;
        padding: 8px;
        border: 1px solid #004FCA;
        border-radius: 5px;
        color: #004FCA;
        cursor: pointer;
        width: 35%;
        height: 38px;
        font-size: 18px;
    }
    input[type="file"]::file-selector-button:hover{
        color: white;
        background-color: #004FCA;
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
            <div class="warp-item"><a class="active" href=""><i class="fa fa-star"
                        style="font-size:24px"></i>&nbsp;&nbsp;&nbsp;VoxToLua</a></div>
            <div class="warp-item"><a href="formatscript.php"><i class="fa fa-file-code-o"
                        style="font-size:24px"></i>&nbsp;&nbsp;&nbsp;FormatScript</a>

            </div>
        </div>
        <div class="item">
            <div class="showpage">
                <div class="pagename"><a>VoxToLua</a></div>
                <div class="menu" onclick="menu(this)"><i class="fa fa-reorder" style="font-size:34px"></i></div>
            </div>
            <div class="flex-respone">
                <div class="normal-box" style="width:45%;">
                    <a class="topic-box">.obj -> .vox</a>
                    <hr align="left"></br>
                    <iframe id="objvox" src="" title="voxelizer, convert your 3D model or image to voxels online" width="100%" height="620px" scrolling="off"></iframe>
                </div>
                <div class="normal-box" style="width:45%;">
                    <a class="topic-box">.obj / .vox / image -> .txt</a>
                    <hr align="left"></br>
                    <iframe id="modeltxt" src="" title="voxelizer, convert your 3D model or image to voxels online" width="100%" height="620px" scrolling="off"></iframe>
                </div>
                <div class="normal-box" style="width:45%;">
                    <a class="topic-box">TXT -> Lua Script</a>
                    <hr align="left"></br>
                    <div align="left">
                        <input id="UploadFile" type="file" name="UploadFile" accept=".txt" required></br></br>
                        Color Block:</br>
                        <select id="block-select" name="block-select" class="my-input" aria-label="Default select example" required>
                            <option value="" disabled>-- Block Color --</option>
                            <option value="667" selected>Concrete</option>
                        </select></br>
                        Create a block in 200ms:
                        <input class="my-input" id="Buffer" type="number" name="Buffer" value="20" required>   block</br></br>
                        <button id="submit-btn" onclick="send_data(this)" class="normal-btn">Text -> Script</button>
                        </br>
                        1. Click any position.</br>
                        2. use command "!sct start" to run script</br>
                        *  use command "!sct cancel" to cancel</br>
                    </div>
                </div>
                </br></br></br>
                <div id="scriptlua" class="hide" style="width:60%;">
                    <div id="data-script"></div>
                </div>
                
            </div>
        </div>
    </div>
    <script src="myscript3.js"></script>
    <script>
        sleep(100).then(() => {
            getelembyid("objvox").setAttribute("src", "voxelizer/?out=vox");
            getelembyid("modeltxt").setAttribute("src", "voxelizer/?out=txt");
        });
    </script>
    <script>
    function selectText(ele) {
        ele.select();
        document.execCommand("copy");
    }
    </script>
    <script type="text/javascript">
        let data_voxel = "";
        const fileInput = document.getElementById('UploadFile');
        fileInput.addEventListener('change', function() {
            const selectedFile = fileInput.files[0];
            if (selectedFile) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const content = event.target.result;
                    data_voxel = content;
                };
                reader.readAsText(selectedFile);
            }
        });
        function send_data(form){
            document.getElementById("scriptlua").className = "hide";
            loader("submit-btn", "loading-page", "status_noti", "status_text", "Wait", "load");
            var form_data = new FormData();
            form_data.append("file", data_voxel);
            form_data.append("file_tmp", fileInput.files[0]);
            form_data.append("file-name", document.getElementById('UploadFile').value);
            form_data.append("block-select", document.getElementById("block-select").value);
            form_data.append("Buffer", document.getElementById("Buffer").value);
            $.ajax({
                url: 'process.php',
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
                            loader("submit-btn", "loading-page", "status_noti", "status_text", "Block count need > 100", "warn");
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