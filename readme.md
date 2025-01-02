### oceanengine refresh token
*/30 * * * *  cd /www/wwwdata/qitt/ && /usr/bin/php think oceanengine:AccessToken
*/40 * * * * cd /www/wwwdata/qitt/ &&  /usr/bin/php think oceanengine:GetAdvertiserList
*/3 * * * * cd /www/wwwdata/qitt/ &&  /usr/bin/php think oceanengine:GetLifeClueList

*/5 * * * * cd /www/wwwdata/qitt/ &&  /usr/bin/php think douyin:AccessToken
*/6 * * * * cd /www/wwwdata/qitt/ &&  /usr/bin/php think douyin:GetLocalLifeList

###回调地址
http://47.99.101.238/bytedance/oceanengine/callback