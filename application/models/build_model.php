<?php

if (! defined('BASEPATH')) exit('No direct script access');

class Build_model extends MY_Model {

  //php 5 constructor
  function __construct() 
  {
    parent::__construct();
    $this->strip_tags = FALSE;
  }


  function before_insert( $data )
  {	
    $data = $this->clean( $data );
    $this->load->helper( 'string' );

    // save twitter user with buid if we got one...
    if ( $this->tweet->logged_in() )
    {
      $tokens = $this->tweet->get_tokens();
      $user = $this->tweet->call('get', 'account/verify_credentials');
      $data['twitter_screen_name'] = $user->screen_name;
      $data['twitter_id_str'] = $user->id_str;
      $data['twitter_profile_image_url'] = $user->profile_image_url;
    }

    $data[ 'created' ] = time();
    $data[ 'modified' ] = time();
    $data[ 'last_viewed' ] = time();
    $data[ 'view_key' ] = random_string( 'alpha' );
    $data[ 'edit_key' ] = random_string( 'alpha' );
    return $data;
  }

  function find_by_key( $key )
  {
    $q = $this->db->where( 'edit_key', $key )->or_where( 'view_key', $key )->limit( 1 )->get( 'builds' );
    $row = $q->row_array();
    if( !empty( $row )){
      $row[ 'mode' ] = 'edit';
      if( $key == $row[ 'view_key' ] ){
        $row[ 'mode' ] = 'view';
      }
    }
    return $row;
  }

  function before_update( $data )
  {
    $data = $this->clean( $data );
    $data[ 'modified' ] = time();
    $row = parent::get_by_id( $data[ 'id' ] );
    $data[ 'edits' ] = $row[ 'edits' ] + 1;
    return $data;
  }

  function update_name( $data )
  {
    // GOTTA MAKE SURE THAT THE TWITTER SESSION MATCHES THE TWITTER FOR THE BUILD
    $build = $this->get_by_id( $data['id']);
    if( empty($build) ){
      return FALSE;
    }
    
    if( !$this->tweet->logged_in() ){
      return FALSE;
    }else{
      
      $tokens = $this->tweet->get_tokens();
      $user = $this->tweet->call('get', 'account/verify_credentials');
      
      if( $user->id_str != $build['twitter_id_str']){
        return FALSE;
      }else{
        $this->update( $data );
        return TRUE;
      }
    }
  }

  function update_views( $row )
  {
    $row[ 'id' ];
    $data[ 'views' ] = $row[ 'views' ] + 1;
    $data[ 'last_viewed' ] = time();
    $this->db->where( 'id', $row['id'] )->update( 'builds', $data );
  }

  function get_id_from_key( $edit_key )
  {
    $q = $this->db->select( 'id' )->where( 'edit_key', $edit_key )->limit( 1 )->get( 'builds' );
    $row = $q->row_array();
    return $row[ 'id' ];
  }

  function clean( $data )
  {
    if( isset( $data[ 'html' ] ))
    {
      //$data[ 'html' ] = preg_replace("@<!@", "#!#", $data[ 'html' ]);
      $data[ 'html' ] = encode_php_tags( $data[ 'html' ] );

      $data[ 'html' ] = strip_only_tags( $data[ 'html' ], MAL_TAGS, FALSE ); 

      //        $data[ 'html' ] = preg_replace("@<!--@", "#!--#", $data[ 'html' ]);
      // 
      //       $data[ 'html' ] = strip_tags( $data[ 'html' ], ALLOWED_TAGS );
      // 
      //       $data[ 'html' ] = preg_replace("@#\!#@", "<!", $data[ 'html' ]);
      //       $data[ 'html' ] = preg_replace("@#!--#@", "<!--", $data[ 'html' ]);
    }

    if( isset( $data[ 'css' ] ))
    {
      $data[ 'css' ] = strip_tags( $data[ 'css' ] );
    }
    return $data;
  }
}