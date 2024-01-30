<?php
session_start();
        if(isset($_SESSION['user'])){
            echo json_encode(['success' => true ]);
        }else{
            echo json_encode(['success' => false]);
        }
    