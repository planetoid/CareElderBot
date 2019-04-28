var ss = SpreadsheetApp.getActiveSpreadsheet(),
    sheet1 = ss.getSheetByName("sheet1"); // "sheet1" 改成你的工作表名稱

function doPost(e) {
    var para = e.parameter, // 存放 post 所有傳送的參數
        method = para.method;

    if (method == "write") {
        write_data(para);
    }

    if (method == "read") {
        // 這裡放讀取資料的語法 下一篇說明
    }

    // Return plain text Output
    return ContentService.createTextOutput("success");

}

function write_data(para) {
    // https://developers.google.com/apps-script/reference/utilities/utilities#formatdatedate-timezone-format
    // https://stackoverflow.com/questions/49696786/apps-script-formatting-the-current-date-for-timestamp
    var timestamp = Utilities.formatDate(new Date(), "GMT+8", "yyyy/MM/dd HH:mm:ss"),
        message = para.message,
        user_id = para.user_id,
        action_name = para.action_name,
        action_value = para.action_value,
        notes = para.notes;
    sheet1.appendRow([timestamp, message, user_id, action_name, action_value, notes]); // 插入一列新的資料

}

function test(){
    var e = {
        parameter:{
            "method": "write",
            "message": "長文",
            "user_id": "1234",
            "action_name": "vote",
            "action_value": 1,
            "notes": "測試寫入功能"
        }
    }
    doPost(e);
}