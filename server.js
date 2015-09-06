/**
 * Created by donastephen on 9/2/15.
 */
var express = require('express');
app = express();
bodyParser = require('body-parser');
mongoose = require('mongoose');

meetupsController = require('./server/controllers/meetups-controllers');

mongoose.connect('mongodb://localhost:/mean-demo');

app.use(bodyParser());
app.get('/', function(req,res){
    res.sendfile(__dirname+'/client/views/index.html');
})

app.use('/js', express.static(__dirname +'/client/js'));
app.post('/api/meetups',meetupsController.create)

app.listen(3000, function(){
    console.log('I am listening');
})