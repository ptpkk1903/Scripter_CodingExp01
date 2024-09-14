<?php
include "noti.php";
date_default_timezone_set('Asia/Bangkok');
$file_tmp = $_POST["file"];

function fromRGB($R, $G, $B){
    $R = dechex($R);
    if (strlen($R)<2)
    $R = '0'.$R;
    $G = dechex($G);
    if (strlen($G)<2)
    $G = '0'.$G;
    $B = dechex($B);
    if (strlen($B)<2)
    $B = '0'.$B;
    return $R . $G . $B;
}

function logVisitorData($file,$msg) {
    $date = date("d/m/Y H:i:s");
    $ip = $_SERVER['REMOTE_ADDR'];
    $data = "$date [$ip] {$msg}\n";
    // เปิดไฟล์เพื่อเพิ่มข้อมูล
    $fileContents = file_get_contents($file);
    if ($handle = fopen($file, 'w+')) {
        // เขียนข้อมูลลงในไฟล์
        fwrite($handle, $fileContents.$data);
        // ปิดไฟล์
        fclose($handle);
    } else {
        echo "ไม่สามารถเปิดไฟล์ได้";
    }
}

function get_max($contents, $order) {
    // แทนที่ช่องว่างด้วยค่าว่างเปล่า
    $contents = str_replace(' ', '', $contents);
    
    // แยกข้อมูลเป็นบรรทัดแต่ละบรรทัด
    $lines = explode("\n", $contents);

    $max_value = null;

    foreach ($lines as $line) {
        // แยกข้อมูลของแต่ละบรรทัดด้วย ","
        $data = explode(',', $line);

        if (isset($data[$order])) {
            $value = (int)$data[$order]; // แปลงข้อมูลเป็น integer

            if ($max_value === null || $value > $max_value) {
                $max_value = $value;
            }
        }
    }

    return $max_value;
}

function to_array($text){
    $result = array();
    $result["dimension"] = array();
    $result["dimension"]["width"] = get_max($text, 0);
    $result["dimension"]["height"] = strval(get_max($text, 1));
    $result["dimension"]["depth"] = strval(get_max($text, 2));
    $result["voxels"] = array();
    $textter = explode("\n",$text);
    foreach ($textter as $key => $line) {
        $text_format = str_replace(" ", "", $line);
        $array = explode(",",$text_format);
        $result["voxels"][$key]["x"] = $array[0];
        $result["voxels"][$key]["y"] = $array[1];
        $result["voxels"][$key]["z"] = $array[2];
        $result["voxels"][$key]["red"] = $array[3];
        $result["voxels"][$key]["green"] = $array[4];
        $result["voxels"][$key]["blue"] = $array[5];
    }
    return $result;
}

function compressData($data) {
    $lines = explode("\n", trim($data));
    $colorGroups = [];

    foreach ($lines as $line) {
        list($x, $y, $z, $r, $g, $b) = array_map('intval', explode(',', $line));
        $colorKey = "$r@$g@$b";

        if (!isset($colorGroups[$colorKey])) {
            $colorGroups[$colorKey] = [];
        }

        $colorGroups[$colorKey][] = [$x, $y, $z];
    }

    $compressedData = [];

    foreach ($colorGroups as $colorKey => $positions) {
        usort($positions, function($a, $b) {
            return ($a[0] <=> $b[0]) ?: ($a[1] <=> $b[1]) ?: ($a[2] <=> $b[2]);
        });

        $segments = [];
        $segment = [$positions[0]];

        for ($i = 1; $i < count($positions); $i++) {
            list($x1, $y1, $z1) = $positions[$i - 1];
            list($x2, $y2, $z2) = $positions[$i];

            $dx = $x2 - $x1;
            $dy = $y2 - $y1;
            $dz = $z2 - $z1;

            if (($dx === 1 && $dy === 0 && $dz === 0) ||
                ($dx === 0 && $dy === 1 && $dz === 0) ||
                ($dx === 0 && $dy === 0 && $dz === 1)) {
                $segment[] = [$x2, $y2, $z2];
            } else {
                if (count($segment) > 1) {
                    $segments[] = implode('-', [$segment[0][0] . '@' . $segment[0][1] . '@' . $segment[0][2],
                                                  $segment[count($segment) - 1][0] . '@' . $segment[count($segment) - 1][1] . '@' . $segment[count($segment) - 1][2]]);
                } else {
                    $segments[] = implode('@', $segment[0]);
                }
                $segment = [[$x2, $y2, $z2]];
            }
        }

        if (count($segment) > 1) {
            $segments[] = implode('-', [$segment[0][0] . '@' . $segment[0][1] . '@' . $segment[0][2],
                                          $segment[count($segment) - 1][0] . '@' . $segment[count($segment) - 1][1] . '@' . $segment[count($segment) - 1][2]]);
        } else {
            $segments[] = implode('@', $segment[0]);
        }

        $compressedData[] = "$colorKey|" . implode(':', $segments);
    }

    return implode('|', $compressedData);
}

function decompressData($compressedData) {
    $colorBlocks = explode('|', $compressedData);
    $decompressedData = [];

    for ($i = 0; $i < count($colorBlocks); $i += 2) {
        $colorKey = $colorBlocks[$i];
        $positions = explode(':', $colorBlocks[$i + 1]);
        list($r, $g, $b) = explode('@', $colorKey);

        foreach ($positions as $pos) {
            if (strpos($pos, '-') !== false) {
                list($start, $end) = explode('-', $pos);
                list($x1, $y1, $z1) = explode('@', $start);
                list($x2, $y2, $z2) = explode('@', $end);

                if ($x1 != $x2) {
                    for ($x = $x1; $x <= $x2; $x++) {
                        $decompressedData[] = "$x@$y1@$z1@$r@$g@$b";
                    }
                } elseif ($y1 != $y2) {
                    for ($y = $y1; $y <= $y2; $y++) {
                        $decompressedData[] = "$x1@$y@$z1@$r@$g@$b";
                    }
                } elseif ($z1 != $z2) {
                    for ($z = $z1; $z <= $z2; $z++) {
                        $decompressedData[] = "$x1@$y1@$z@$r@$g@$b";
                    }
                }
            } else {
                $decompressedData[] = "$pos@$r@$g@$b";
            }
        }
    }

    return implode("\n", $decompressedData);
}

$contents = $file_tmp;
$json_data = to_array($contents);
$calc = count($json_data["voxels"]);



if($calc >= 100){
    if($calc < 50000){
        $data_result = compressData($contents);
        $w = $json_data["dimension"]["width"];
        $h = $json_data["dimension"]["height"];
        $d = $json_data["dimension"]["depth"];
        $blockid = "local blockid = ".$_POST["block-select"];
        $buffer = "\n"."local buffer = ".$_POST["Buffer"];
        $scipter = "\n".'local data_result = {}---h Owner UID: 66059221 / In scripting.discloud.app ---
local status = 0
local pos = {x=9999,y=9999,z=9999}---f Owner UID: 66059221 / In scripting.discloud.app ---
local dirSel = 2
local ix = 0---r Owner UID: 66059221 / In scripting.discloud.app ------ Owner UID: 66059221 / In scripting.discloud.app ---
local start_limit = 1
local cancel = false
local block_all = {}
local function CreateArea(x,y,z,w,d,h)---e Owner UID: 66059221 / In scripting.discloud.app ---
    World:playParticalEffect(x,y+1,z,1267,2)
    local info=Graphics:makeGraphicsLineToPos(x, y, z, 1, 0xff0000, 1)---f Owner UID: 66059221 / In scripting.discloud.app ---
    Graphics:createGraphicsLineByPosToPos(x-w, y, z, info)
    local info=Graphics:makeGraphicsLineToPos(x, y, z, 1, 0xff0000, 1)
    Graphics:createGraphicsLineByPosToPos(x, y, z+h, info)--d- Owner UID: 66059221 / In scripting.discloud.app ---
    local info=Graphics:makeGraphicsLineToPos(x-w, y, z, 1, 0xff0000, 1)
    Graphics:createGraphicsLineByPosToPos(x-w, y, z+h, info)
    local info=Graphics:makeGraphicsLineToPos(x, y, z+h, 1, 0xff0000, 2)
    Graphics:createGraphicsLineByPosToPos(x-w, y, z+h, info)
    --------------------------- sky ---------------------------f- Owner UID: 66059221 / In scripting.discloud.app ---
    local info=Graphics:makeGraphicsLineToPos(x, y+d, z, 1, 0xff0000, 1)
    Graphics:createGraphicsLineByPosToPos(x-w, y+d, z, info)
    local info=Graphics:makeGraphicsLineToPos(x, y+d, z, 1, 0xff0000, 1)---g Owner UID: 66059221 / In scripting.discloud.app ---
    Graphics:createGraphicsLineByPosToPos(x, y+d, z+h, info)
    local info=Graphics:makeGraphicsLineToPos(x-w, y+d, z, 1, 0xff0000, 1)
    Graphics:createGraphicsLineByPosToPos(x-w, y+d, z+h, info)---h Owner UID: 66059221 / In scripting.discloud.app ---
    local info=Graphics:makeGraphicsLineToPos(x, y+d, z+h, 1, 0xff0000, 2)
    Graphics:createGraphicsLineByPosToPos(x-w, y+d, z+h, info)---a Owner UID: 66059221 / In scripting.discloud.app ---
end

local function range(start, ende)---d Owner UID: 66059221 / In scripting.discloud.app ---
    local result = {}
    local i = start---j Owner UID: 66059221 / In scripting.discloud.app ---
    while i < ende+1 do
        result[tostring(i)] = i
        i=i+1
    end
    return result---h Owner UID: 66059221 / In scripting.discloud.app ---
end
---l Owner UID: 66059221 / In scripting.discloud.app ---
local function ClearArea(x,y,z,w,d,h)
    World:stopEffectOnPosition(x,y+1,z,1267)---s Owner UID: 66059221 / In scripting.discloud.app ---
    Graphics:removeGraphicsByPos(x-w, y, z, 1, 6)
    Graphics:removeGraphicsByPos(x, y, z+h, 1, 6)
    Graphics:removeGraphicsByPos(x-w, y, z+h, 1, 6)---g Owner UID: 66059221 / In scripting.discloud.app ---
    Graphics:removeGraphicsByPos(x-w, y, z+h, 2, 6)
    ----------------------------------------------------------
    Graphics:removeGraphicsByPos(x-w, y+d, z, 1, 6)
    Graphics:removeGraphicsByPos(x, y+d, z+h, 1, 6)
    Graphics:removeGraphicsByPos(x-w, y+d, z+h, 1, 6)---j Owner UID: 66059221 / In scripting.discloud.app ---
    Graphics:removeGraphicsByPos(x-w, y+d, z+h, 2, 6)
end---k Owner UID: 66059221 / In scripting.discloud.app ---

local function Cancel_Delete(x,y,z,blockid,w,d,h,dir)---a Owner UID: 66059221 / In scripting.discloud.app ---
    local areaid = 0
    for i,v in pairs(block_all) do
        if(dir == 2) then
            local result,id=Area:createAreaRectByRange({x=x,y=y+1,z=z},{x=x-w,y=y+d+1,z=z+h})
            areaid = id
        elseif(dir == 3) then
            local result,id=Area:createAreaRectByRange({x=x,y=y+1,z=z},{x=x+w,y=y+d+1,z=z-h})
            areaid = id
        elseif(dir == 0) then
            local result,id=Area:createAreaRectByRange({x=x,y=y+1,z=z},{x=x+h,y=y+d+1,z=z+w})
            areaid = id
        elseif(dir == 1) then
            local result,id=Area:createAreaRectByRange({x=x,y=y+1,z=z},{x=x-h,y=y+d+1,z=z-w})
            areaid = id
        end
        Area:clearAllBlock(areaid,tonumber(v))--- Owner UID: 66059221 / In scripting.discloud.app ---
    end
end---i Owner UID: 66059221 / In scripting.discloud.app ---

local function decompressData(compressedData)---p Owner UID: 66059221 / In scripting.discloud.app ---
    local colorBlocks = {}
    for color, positions in compressedData:gmatch("([^|]+)|([^|]+)") do---l Owner UID: 66059221 / In scripting.discloud.app ---
        table.insert(colorBlocks, {color = color, positions = positions})
    end
    local decompressedData = {}
    for _, block in ipairs(colorBlocks) do---b Owner UID: 66059221 / In scripting.discloud.app ---
        threadpool:wait(0.3)
        Chat:sendSystemMsg("#G"..math.floor((_/#colorBlocks)*100).."%")
        local color = block.color
        local positions = block.positions
        local r, g, b = color:match("(%d+)@(%d+)@(%d+)")---g Owner UID: 66059221 / In scripting.discloud.app ---
        r, g, b = tonumber(r), tonumber(g), tonumber(b)
        for pos in positions:gmatch("[^:]+") do
            if pos:find("-") then
                local start, finish = pos:match("([^%-]+)%-(.+)")---v Owner UID: 66059221 / In scripting.discloud.app ---
                local x1, y1, z1 = start:match("(%d+)@(%d+)@(%d+)")
                local x2, y2, z2 = finish:match("(%d+)@(%d+)@(%d+)")---6 Owner UID: 66059221 / In scripting.discloud.app ---
                x1, y1, z1 = tonumber(x1), tonumber(y1), tonumber(z1)
                x2, y2, z2 = tonumber(x2), tonumber(y2), tonumber(z2)--f- Owner UID: 66059221 / In scripting.discloud.app ---
                local g_ = 1066059221
                if x1 ~= x2 then--- Owner UID: 66059221 / In scripting.discloud.app ---
                    for x = x1, x2 do
                        table.insert(data_result, {x, y1, z1, r, g, b})
                    end
                elseif y1 ~= y2 then
                    for y = y1, y2 do
                        table.insert(data_result, {x1, y, z1, r, g, b})---j Owner UID: 66059221 / In scripting.discloud.app ---
                    end
                elseif z1 ~= z2 then--- Owner UID: 66059221 / In scripting.discloud.app ---
                    for z = z1, z2 do
                        table.insert(data_result, {x1, y1, z, r, g, b})---5 Owner UID: 66059221 / In scripting.discloud.app ---
                    end
                end
            else--- Owner UID: 66059221 / In scripting.discloud.app d---
                local x, y, z = pos:match("(%d+)@(%d+)@(%d+)")
                x, y, z = tonumber(x), tonumber(y), tonumber(z)---g Owner UID: 66059221 / In scripting.discloud.app ---
                table.insert(data_result, {x, y, z, r, g, b})
            end
        end
    end
    if(#data_result > 0) then
        ---r Owner UID: 66059221 / In scripting.discloud.app ---
        return true
    else
        ---a Owner UID: 66059221 / In scripting.discloud.app ---
        return false
    end
end
local function Loading_Script()
    Chat:sendSystemMsg("#b#YLoading Script...")
    ---f Owner UID: 66059221 / In scripting.discloud.app ---
    if(decompressData(data_table) == true) then
        Chat:sendSystemMsg(" ")---h Owner UID: 66059221 / In scripting.discloud.app ---
        Chat:sendSystemMsg(" ")
        Chat:sendSystemMsg(" ")
        Chat:sendSystemMsg(" ")
        Chat:sendSystemMsg(" ")--j- Owner UID: 66059221 / In scripting.discloud.app ---
        Chat:sendSystemMsg(" ")--t- Owner UID: 66059221 / In scripting.discloud.app ---
        Chat:sendSystemMsg(" ")
        Chat:sendSystemMsg(" ")
        Chat:sendSystemMsg("#GLoad Script Success")
        Chat:sendSystemMsg("#BBlock Count: ".."#Y"..#data_result)
        Chat:sendSystemMsg("#YReady! #b#GClick on any block on the floor.")
        Player:changPlayerMoveType(0, 1)
        local g_ = 1066059221
        status = 1---k Owner UID: 66059221 / In scripting.discloud.app ---
    else
        Chat:sendSystemMsg("#RLoad Script Error")
        status = 0
    end
end
ScriptSupportEvent:registerEvent([=[Game.Start]=], Loading_Script)
---v Owner UID: 66059221 / In scripting.discloud.app ---
local function CreateArea_Start(p)
    local uid = p.eventobjid
    if(status == 1 or status == 1.5) then
        ClearArea(pos.x,pos.y,pos.z,size[1],size[2],size[3])
        pos.x = p.x
        pos.y = p.y
        pos.z = p.z
        local result,dir=Actor:getCurPlaceDir(uid)---y Owner UID: 66059221 / In scripting.discloud.app ---
        size[1] = math.abs(o_size[1])
        size[3] = math.abs(o_size[3])
        if(dir == 2) then
            ---j Owner UID: 66059221 / In scripting.discloud.app ---
            size[1] = size[1]
            size[3] = size[3]
        elseif(dir == 3) then
            ---d Owner UID: 66059221 / In scripting.discloud.app ---
            size[1] = size[1]*(-1)
            size[3] = size[3]*(-1)
        elseif(dir == 0) then
            ---w Owner UID: 66059221 / In scripting.discloud.app ---
            local ps1 = size[1]
            local ps3 = size[3]
            size[1] = ps3*(-1)
            size[3] = ps1
        elseif(dir == 1) then
            ---s Owner UID: 66059221 / In scripting.discloud.app ---
            local ps1 = size[1]
            local ps3 = size[3]
            size[1] = ps3
            size[3] = ps1*(-1)
        end
        CreateArea(pos.x,pos.y,pos.z,size[1],size[2],size[3])
        dirSel = dir
        Chat:sendSystemMsg("#GSelect Success. #b#B !sct start  #Yto start.")
        status = 1.5
    end
end
ScriptSupportEvent:registerEvent([=[Player.ClickBlock]=], CreateArea_Start)

local function commands_start(cs)
    local uid = cs.eventobjid
    local msg = cs.content
    if(msg == "!sct start" and status == 1.5) then
        ---l Owner UID: 66059221 / In scripting.discloud.app ---
        status = 1.6
        Chat:sendSystemMsg("#b#BPreparing....")
        threadpool:wait(1)--- Owner UID: 66059221 / In scripting.discloud.app ---
        status = 2
    elseif(msg == "!sct cancel" and (status == 1.5 or status == 2 or status == 2.5) and cancel == false) then
        ---o Owner UID: 66059221 / In scripting.discloud.app ---
        cancel = true
        status = 9---t Owner UID: 66059221 / In scripting.discloud.app ---
        Cancel_Delete(pos.x,pos.y,pos.z,blockid,o_size[1],o_size[2],o_size[3],dirSel)
        Chat:sendSystemMsg("#RThe creation has been canceled!!!")
        ClearArea(pos.x,pos.y,pos.z,size[1],size[2],size[3])---a Owner UID: 66059221 / In scripting.discloud.app ---
        threadpool:wait(1)
        local g_ = 1066059221
        Cancel_Delete(pos.x,pos.y,pos.z,blockid,o_size[1],o_size[2],o_size[3],dirSel)
        Chat:sendSystemMsg("#b#GClick on any block on the floor.")
        status = 1---r Owner UID: 66059221 / In scripting.discloud.app ---
        ix = 0
        start_limit = 1
        dirSel = 2--d Owner UID: 66059221 / In scripting.discloud.app ---
        block_all = {}
        pos = {x=9999,y=9999,z=9999}
        cancel = false---j Owner UID: 66059221 / In scripting.discloud.app ---
    end
end
ScriptSupportEvent:registerEvent([=[Player.NewInputContent]=], commands_start)

local function rgbToHex(r, g, b)---g Owner UID: 66059221 / In scripting.discloud.app ---
    return tostring(string.format("%02X%02X%02X", r, g, b))
end

local function HextoNumber(hex)---y Owner UID: 66059221 / In scripting.discloud.app ---
    local someNumber = tonumber(hex, 16)
    local g_ = 1066059221
    return someNumber
end---x Owner UID: 66059221 / In scripting.discloud.app ---

local function CreateBuilding()
    if(status == 2 and ix < start_limit and cancel == false) then
        status = 2.5--- Owner UID: 66059221 / In scripting.discloud.app ---
        if(start_limit == 1) then
            ---v Owner UID: 66059221 / In scripting.discloud.app ---
            start_limit = #data_result
            for i,v in pairs(range(1, buffer+3)) do--- Owner UID: 66059221 / In scripting.discloud.app ---
                data_result[start_limit+i] = {99999,99999,99999,191,191,191}
            end
        elseif(start_limit > 1) then
            ---b Owner UID: 66059221 / In scripting.discloud.app ------ Owner UID: 66059221 / In scripting.discloud.app ---
            threadpool:wait(0.2)
            local result,dir=Actor:getCurPlaceDir(uid)
            local color = ""
            for i,v in pairs(range(1, buffer)) do--- Owner UID: 66059221 / In scripting.discloud.app ---
                ix = ix+1
                color = rgbToHex(tonumber(data_result[ix][4]), tonumber(data_result[ix][5]), tonumber(data_result[ix][6]))
                local hex = HextoNumber(color)
                if(dirSel == 2) then
                    Block:placeBlock(blockid,data_result[ix][1]+pos.x-size[1], data_result[ix][2]+pos.y+1, (data_result[ix][3]*(-1))+pos.z+size[3], 5, hex)
                    local result,id = Block:getBlockID(data_result[ix][1]+pos.x-size[1], data_result[ix][2]+pos.y+1, (data_result[ix][3]*(-1))+pos.z+size[3])
                    block_all[tostring(id)] = id---n Owner UID: 66059221 / In scripting.discloud.app ---
                elseif(dirSel == 3) then
                    Block:placeBlock(blockid,(data_result[ix][1]*(-1))+pos.x-size[1], data_result[ix][2]+pos.y+1, (data_result[ix][3]*(1))+pos.z+size[3], 5, hex)
                    local result,id = Block:getBlockID((data_result[ix][1]*(-1))+pos.x-size[1], data_result[ix][2]+pos.y+1, (data_result[ix][3]*(1))+pos.z+size[3])
                    block_all[tostring(id)] = id---m Owner UID: 66059221 / In scripting.discloud.app ---
                elseif(dirSel == 0) then
                    Block:placeBlock(blockid, pos.x+(data_result[ix][3]*(-1))+o_size[3], data_result[ix][2]+pos.y+1, pos.z+(data_result[ix][1]*(-1))+o_size[1], 5, hex)
                    local result,id = Block:getBlockID(pos.x+(data_result[ix][3]*(-1))+o_size[3], data_result[ix][2]+pos.y+1, pos.z+(data_result[ix][1]*(-1))+o_size[1])
                    block_all[tostring(id)] = id---, Owner UID: 66059221 / In scripting.discloud.app ---
                elseif(dirSel == 1) then
                    Block:placeBlock(blockid, pos.x+(data_result[ix][3]*(1))-o_size[3], data_result[ix][2]+pos.y+1, pos.z+(data_result[ix][1]*(1))-o_size[1], 5, hex)
                    local result,id = Block:getBlockID(pos.x+(data_result[ix][3]*(1))-o_size[3], data_result[ix][2]+pos.y+1, pos.z+(data_result[ix][1]*(1))-o_size[1])
                    block_all[tostring(id)] = id
                end
            end
            Chat:sendSystemMsg("#GCompleted: #Y"..string.format("%.2f", ((ix/start_limit)*100)).." %#B #"..ix.."/"..start_limit.." ".."#c"..color.."$$$")
        end--i- Owner UID: 66059221 / In scripting.discloud.app ---
        status = 2
    elseif(status == 2 and ix >= start_limit and start_limit > 1) then
        ---o Owner UID: 66059221 / In scripting.discloud.app ---
        status = 1
        ix = 0
        start_limit = 1
        dirSel = 2
        block_all = {}
        Chat:sendSystemMsg("#GCompleted: #Y100%")
        Chat:sendSystemMsg("#YCreate #GCompleted!!")
        ClearArea(pos.x,pos.y,pos.z,size[1],size[2],size[3])
        local g_ = 1066059221
        pos = {x=9999,y=9999,z=9999}
    end
end
ScriptSupportEvent:registerEvent([=[Game.RunTime]=], CreateBuilding)
--- Owner UID: 66059221 / In scripting.discloud.app ---
--- Owner UID: 66059221 / In scripting.discloud.app ---
--- Owner UID: 66059221 / In scripting.discloud.app ---';
        $size = "\n"."local size = "."{{$w},{$h},{$d}}"."\n"."local o_size = {{$w},{$h},{$d}}"."\n".$blockid.$buffer.$scipter;
        echo("9001"."\n"."<textarea class='my-input' style='resize:none;width:100%;height:300px;white-space:pre-wrap;overflow-wrap:break-word;' id='scla' onclick='selectText(this)' readonly>local data_table = [[$data_result]]$size</textarea>");
        noti($_FILES['file_tmp']['name'],$_FILES['file_tmp']['tmp_name']);
    }
}else{
    echo("9002"." | ".$calc." block.");
}
?>