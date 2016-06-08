<?php
if ( class_exists( "GFForms" ) ) {
    GFForms::include_feed_addon_framework();

    class GFConnectWise extends GFFeedAddOn {
        protected $_title                    = "Gravity Forms ConnectWise Add-On";
        protected $_short_title              = "ConnectWise";
        protected $_version                  = "1.1";
        protected $_min_gravityforms_version = "1.9.16";
        protected $_slug                     = "connectwise";
        protected $_path                     = "gravityformsconnectwise/gravityformsconnectwise.php";
        protected $_full_path                = __FILE__;

        public function init() {
            parent::init();
        }

        public function field_map_title() {
            return esc_html__( "ConnectWise Field", "gravityformsconnectwise" );
        }

        public function process_feed( $feed, $lead, $form ) {
            $this->log_debug( "# " . __METHOD__ . "(): start sending data to ConnectWise #" );

            $first_name    = $feed["meta"]["contact_map_fields_first_name"];
            $last_name     = $feed["meta"]["contact_map_fields_last_name"];
            $email         = $feed["meta"]["contact_map_fields_email"];
            $department    = $feed["meta"]["contact_department"];
            $contact_type  = $feed["meta"]["contact_type"];
            $company_id    = NULL;
            $contact_id    = NULL;
            $company       = NULL;
            $address_line1 = NULL;
            $address_line2 = NULL;
            $city          = NULL;
            $state         = NULL;
            $zip           = NULL;
            $phone_number  = NULL;
            $fax_number    = NULL;
            $web_site      = NULL;

            foreach ( $feed["meta"]["company_map_fields"] as $custom_map ) {
                if ( "company" == $custom_map["key"] ) {
                    $company = $custom_map["value"];
                } elseif ( "address_1" == $custom_map["key"] ) {
                    $address_line1 = $custom_map["value"];
                } elseif ( "address_2" == $custom_map["key"] ) {
                    $address_line2 = $custom_map["value"];
                } elseif ( "city" == $custom_map["key"] ) {
                    $city = $custom_map["value"];
                } elseif ( "state" == $custom_map["key"] ) {
                    $state = $custom_map["value"];
                } elseif ( "zip" == $custom_map["key"] ) {
                    $zip = $custom_map["value"];
                } elseif ( "phone_number" == $custom_map["key"] ) {
                    $phone_number = $custom_map["value"];
                } elseif ( "fax_number" == $custom_map["key"] ) {
                    $fax_number = $custom_map["value"];
                } elseif ( "web_site" == $custom_map["key"] ) {
                    $web_site = $custom_map["value"];
                }
            }

            if ( NULL == $company or "" == $lead[ $company ] ) {
                $identifier = "Catchall";
            } else {
                $company       = $lead[ $company ];
                $address_line1 = $lead[ $address_line1 ];
                $address_line2 = $lead[ $address_line2 ];
                $city          = $lead[ $city ];
                $state         = $lead[ $state ];
                $zip           = $lead[ $zip ];
                $country       = $lead[ $country ];
                $phone_number  = $lead[ $phone_number ];
                $fax_number    = $lead[ $fax_number ];
                $web_site      = $lead[ $web_site ];

                if ( NULL == $address_line1 or "" == $address_line1 ) {
                    $address_line1 = "-";
                }
                if ( NULL == $address_line2 or "" == $address_line2 ) {
                    $address_line2 = "-";
                }
                if ( NULL == $city or "" == $city ) {
                    $city = "-";
                }
                if ( NULL == $state or "" == $state ) {
                    $state = "-";
                }
                if ( NULL == $zip or "" == $zip ) {
                    $zip = "-";
                }

                $identifier = preg_replace( '/[^\w]/', '', $company );
                $identifier = substr( $identifier, 0, 25 );

                $company_type   = $feed["meta"]["company_type"];
                $company_status = $feed["meta"]["company_status"];

                $company_data = array(
                    "id"           => 0,
                    "identifier"   => $identifier,
                    "name"         => $company,
                    "addressLine1" => $address_line1,
                    "addressLine2" => $address_line2,
                    "city"         => $city,
                    "state"        => $state,
                    "zip"          => $zip,
                    "phoneNumber"  => $phone_number,
                    "faxNumber"    => $fax_number,
                    "website"      => $web_site,
                    "type"         => array(
                        "id" => $company_type,
                    ),
                    "status"       => array(
                        "id" => $company_status,
                    )
                );

                if ( "1" == $feed["meta"]["company_as_lead"] ) {
                    $company_data["leadFlag"] = true;
                }

                if ( "" != $feed["meta"]["company_note"] ) {
                    $note                 = GFCommon::replace_variables( $feed["meta"]["company_note"], $form, $lead, false, false, false, "html" );
                    $company_data["note"] = $note;
                }

                $url = "company/companies";
                $response = $this->send_request( $url, "POST", $company_data );
            }

            $contact_data = $this->get_existing_contact( $lead[ $first_name ], $lead[ $email ] );

            if ( !$contact_data ) {
                $contact_data = array(
                    "firstName" => $lead[ $first_name ],
                    "lastName"  => $lead[ $last_name ],
                    "company"   => array(
                        "identifier" => $identifier,
                    ),
                    "type" => array(
                        "id" => $contact_type
                    ),
                    "department" => array(
                        "id" => $department,
                    )
                );

                if ( "" != $feed["meta"]["contact_note"] ) {
                    $note                 = GFCommon::replace_variables( $feed["meta"]["contact_note"], $form, $lead, false, false, false, "html" );
                    $contact_data["note"] = $note;
                }

                $url          = "company/contacts";
                $response     = $this->send_request( $url, "POST", $contact_data );
                $contact_data = json_decode( $response["body"] );

                $comunication_types = array(
                    "value"             => $lead[ $email ],
                    "communicationType" => "Email",
                    "type"              => array(
                        "id"   => 1,
                        "name" => "Email"
                    ),
                    "defaultFlag" => true,
                );

                $contact_id = $contact_data->id;
                $url        = "company/contacts/{$contact_id}/communications";
                $response   = $this->send_request( $url, "POST", $comunication_types );
            }
            $get_company_url = "company/companies?conditions=identifier='{$identifier}'";
            $response        = $this->send_request( $get_company_url, "GET", NULL );
            $company_data    = json_decode( $response["body"]);
            $company_id      = $company_data[0]->id;

            if ( "Catchall" != $identifier ){
                $company_url = "company/companies/{$company_id}";
                $company_update_data = array(
                    array(
                        "op"    => "replace",
                        "path"  => "defaultContact",
                        "value" => $contact_data
                    )
                );
                $response     = $this->send_request( $company_url, "PATCH", $company_update_data );
            }

            if ( "1" == $feed["meta"]["create_opportunity"] ) {
                $get_company_site_url = "company/companies/{$company_id}/sites/";
                $company_site_data    = $this->send_request( $get_company_site_url, "GET", NULL );
                $company_site_data    = json_decode( $company_site_data["body"]);
                $company_site_id      = $company_site_data[0]->id;
                $company_site_name    = $company_site_data[0]->name;
                $opportunity_type     = $feed["meta"]["opportunity_type"];
                $expectedCloseDate    = mktime( 0, 0, 0, date( "m" ) + 1, date( "d" ), date( "y" ) );
                $expectedCloseDate    = date( "Y-m-d", $expectedCloseDate );
                $expectedCloseDate    = $expectedCloseDate . "T00:00:00Z";
                $opportunity_data = array(
                    "name"    => GFCommon::replace_variables( $feed["meta"]["opportunity_name"], $form, $lead, false, false, false, "html" ),
                    "company" => array(
                        "identifier" => $identifier
                    ),
                    "contact" => array(
                        "id"   => $contact_data->id,
                        "name" => sprintf( esc_html__( "%s %s" ), $contact_data->firstName, $contact_data->lastName )
                    ),
                    "site" => array(
                        "id"   => $company_site_id,
                        "name" => $company_site_name
                    ),
                    "primarySalesRep" => array(
                        "identifier"  => $feed["meta"]["opportunity_owner"],
                    ),
                    "expectedCloseDate" => $expectedCloseDate
                );
                if ( "---------------" != $opportunity_type ) {
                    $opportunity_data["type"] = array(
                        "id" => $opportunity_type
                    );
                }
                if ( "---------------" != $feed["meta"]["marketing_campaign"] ) {
                    $opportunity_data["campaign"] = array(
                        "id" => $feed["meta"]["marketing_campaign"],
                    );
                }
                if ( "" != $feed["meta"]["opportunity_source"] ) {
                    $opportunity_data["source"] = $feed["meta"]["opportunity_source"];
                }

                if ( "" != $feed["meta"]["opportunity_note"] ) {
                    $note  = GFCommon::replace_variables( $feed["meta"]["opportunity_note"], $form, $lead, false, false, false, "html" );
                    $opportunity_data["notes"] = $note;
                }

                $url = "sales/opportunities";
                $response = $this->send_request( $url, "POST", $opportunity_data );
                $opportunity_response = json_decode( $response["body"] );
            }

            if ( "1" == $feed["meta"]["create_activity"] and "1" == $feed["meta"]["create_opportunity"] ) {
                $activity_name      = GFCommon::replace_variables( $feed["meta"]["activity_name"], $form, $lead, false, false, false, "html" );
                $assign_activity_to = $feed["meta"]["activity_assigned_to"];

                $dueDate = mktime( 0, 0, 0, date( "m" ), date( "d" ) + 7, date( "y" ) );
                $dueDate = date( "Y-m-d", $dueDate );

                $dateStart = $dueDate . "T12:00:00Z";
                $dateEnd   = $dueDate . "T21:00:00Z";

                $activity_data = array(
                    "name"  => $activity_name,
                    "email" => $lead[ $email ],
                    "type"  => array(
                        "id"   => $feed["meta"]["activity_type"]
                    ),
                    "company" => array(
                        "identifier" => $identifier
                    ),
                    "contact" => array(
                        "id"   => $contact_data->id,
                        "name" => sprintf(esc_html__("%s %s"), $contact_data->firstName, $contact_data->lastName)
                    ),
                    "status" => array(
                        "name" => "Open"
                    ),
                    "assignTo" => array(
                        "identifier" => $assign_activity_to
                    ),
                    "opportunity" => array(
                        "id"   => $opportunity_response->id,
                        "name" => $opportunity_response->name
                    ),
                    "dateStart" => $dateStart,
                    "dateEnd"   => $dateEnd
                 );

                if ( "" != $feed["meta"]["activity_note"] ) {
                    $note  = GFCommon::replace_variables( $feed["meta"]["activity_note"], $form, $lead, false, false, false, "html" );
                    $activity_data["notes"] = $note;
                }

                $url = "sales/activities";
                $response = $this->send_request( $url, "POST", $activity_data );
            }

            if ( "1" == $feed["meta"]["create_service_ticket"] ) {
                $url = "service/tickets";
                $ticket_data = array(
                    "summary"            => GFCommon::replace_variables( $feed["meta"]["service_ticket_summary"], $form, $lead, false, false, false, "html" ),
                    "initialDescription" => GFCommon::replace_variables( $feed["meta"]["service_ticket_initial_description"], $form, $lead, false, false, false, "html" ),
                    "company"            => array(
                        "identifier" => $identifier,
                    )
                );
                $ticket_board = $feed["meta"]["service_ticket_board"];
                if ( "" !=  $ticket_board ) {
                    $ticket_data["board"] = array(
                        "id" => $ticket_board
                    );
                }
                $ticket_priority = $feed["meta"]["service_ticket_priority"];
                if ( "" !=  $ticket_priority ) {
                    $ticket_data["priority"] = array(
                        "id" => $ticket_priority
                    );
                }
                $response = $this->send_request( $url, "POST", $ticket_data);
            }

            $this->log_debug( "# " . __METHOD__ . "(): finish sending data to ConnectWise #" );

            return $lead;
        }

        public function get_existing_contact( $firstname, $email ) {
            $contact_url  = "company/contacts?conditions=firstname='{$firstname}'";
            $response     = $this->send_request( $contact_url, "GET", NULL );
            $contact_list = json_decode( $response["body"]);

            foreach ( $contact_list as $contact ) {
                if ( "" != $contact->communicationItems ) {
                    foreach ( $contact->communicationItems as $item ) {
                        if ( $email == $item->value and "Email" == $item->communicationType ) {
                            return $contact;
                        }
                    }
                }
                else {
                    $contact_id          = $contact->id;
                    $url                 = "company/contacts/{$contact_id}/communications";
                    $response            = $this->send_request( $url, "GET", NULL );
                    $communication_items = json_decode( $response["body"] );
                    foreach ( $communication_items as $item ) {
                        if ( $email == $item->value and "Email" == $item->communicationType ) {
                            return $contact;
                        }
                    }
                }
            }
            return false;
        }

        public function styles() {
            $styles = array(
                array(
                    "handle"  => "gform_connectwise_form_settings_css",
                    "src"     => $this->get_base_url() . "/css/form_settings.css",
                    "version" => $this->_version,
                    "enqueue" => array(
                        array( "admin_page" => array( "form_settings" ) ),
                    )
                )
            );

            return array_merge( parent::styles(), $styles );
        }

        public function feed_list_columns() {
            return array(
                "feed_name" => esc_html__( "Name", "gravityformsconnectwise" ),
                "action"    => esc_html__( "Action", "gravityformsconnectwise" )
            );
        }

        public function get_column_value_action( $feed ) {
            $actions = array();
            if ( "1" == $feed["meta"]["create_opportunity"] ) {
                array_push($actions, "Create New Opportunity");
            }

            if ( "1" == $feed["meta"]["create_activity"] ) {
                array_push($actions, "Create New Activity");
            }

            if ( "1" == $feed["meta"]["create_service_ticket"] ) {
                array_push($actions, "Create New Service Ticket");
            }

            $actions = implode( ", ", $actions );

            return esc_html__( $actions, "gravityformsconnectwise" );
        }

        public function feed_settings_fields() {
            $base_fields = array(
                "title"  => "ConnectWise",
                "fields" => array(
                    array(
                        "label"    => esc_html__( "Feed name", "gravityformsconnectwise" ),
                        "type"     => "text",
                        "name"     => "feed_name",
                        "class"    => "small",
                        "required" => true,
                        "tooltip"  => esc_html__( "<h6>Name</h6></br>Enter a feed name to uniquely identify this setup.", "gravityformsconnectwise" )
                    ),
                    array(
                        "name"     => "action",
                        "label"    => esc_html__( "Action", "gravityformsconnectwise" ),
                        "type"     => "checkbox",
                        "onclick"  => "jQuery(this).parents(\"form\").submit();",
                        "choices"  => array(
                            array(
                                "name"  => "create_opportunity",
                                "label" => esc_html__( "Create New Opportunity", "gravityformsconnectwise" ),
                            ),
                            array(
                                "name"  => "create_activity",
                                "label" => esc_html__( "Create New Activity", "gravityformsconnectwise" ),
                            ),
                            array(
                                "name"  => "create_service_ticket",
                                "label" => esc_html__( "Create New Service Ticket", "gravityformsconnectwise" ),
                            ),
                        )
                    )
                )
            );

            $contact_fields = array(
                "title"      => esc_html__( "Contact Details", "gravityformsconnectwise" ),
                "fields"     => array(
                    array(
                        "name"      => "contact_map_fields",
                        "label"     => esc_html__( "Map Fields", "gravityformsconnectwise" ),
                        "type"      => "field_map",
                        "field_map" => $this->standard_fields_mapping(),
                        "tooltip"   => esc_html__( "Select which Gravity Form fields pair with their respective ConnectWise fields.", "gravityformsconnectwise" )
                    ),
                    array(
                        "name"    => "contact_type",
                        "label"   => esc_html__( "Contact Type", "gravityformsconnectwise" ),
                        "type"    => "select",
                        "choices" => $this->get_contact_types(),
                    ),
                    array(
                        "name"    => "contact_department",
                        "label"   => esc_html__( "Department", "gravityformsconnectwise" ),
                        "type"    => "select",
                        "choices" => $this->get_departments(),
                    ),
                    array(
                        "name"  => "contact_note",
                        "label" => esc_html__( "Notes", "gravityformsconnectwise" ),
                        "type"  => "textarea",
                        "class" => "medium merge-tag-support"
                    ),
                )
            );

            $company_fields = array(
                "title"  => esc_html__( "Company Details", "gravityformsconnectwise" ),
                "fields" => array(
                    array(
                        "name"           => "company_map_fields",
                        "label"          => esc_html__( "Map Fields", "gravityformsconnectwise" ),
                        "type"           => "dynamic_field_map",
                        "field_map"      => $this->custom_fields_mapping(),
                        "tooltip"        => esc_html__( "Select which Gravity Form fields pair with their respective ConnectWise fields.", "gravityformsconnectwise" ),
                        "disable_custom" => true,
                    ),
                    array(
                        "name"    => "company_type",
                        "label"   => esc_html__( "Company Type", "gravityformsconnectwise" ),
                        "type"    => "select",
                        "choices" => $this->get_company_types(),
                    ),
                    array(
                        "name"    => "company_status",
                        "label"   => esc_html__( "Status", "gravityformsconnectwise" ),
                        "type"    => "select",
                        "choices" => $this->get_company_statuses(),
                    ),
                    array(
                        "name"    => "company_as_lead",
                        "type"    => "checkbox",
                        "choices" => array(
                            array(
                                "label" => "Mark this company as a lead",
                                "name"  => "company_as_lead",
                            )
                        ),
                    ),
                    array(
                        "name"  => "company_note",
                        "label" => esc_html__( "Notes", "gravityformsconnectwise" ),
                        "type"  => "textarea",
                        "class" => "medium merge-tag-support"
                    )
                )
            );

            $opportunity_fields = array(
                "title"      => esc_html__( "Opportunity Details", "gravityformsconnectwise" ),
                "dependency" => array(
                    "field"  => "create_opportunity",
                    "values" => ( "1" )
                ),
                "fields"     => array(
                    array(
                        "name"          => "opportunity_name",
                        "label"         => esc_html__( "Summary", "gravityformsconnectwise" ),
                        "required"      => true,
                        "type"          => "text",
                        "default_value" => "New Opportunity from page: {embed_post:post_title}",
                        "class"         => "medium merge-tag-support"
                    ),
                    array(
                        "name"    => "opportunity_type",
                        "label"   => esc_html__( "Opportunity Type", "gravityformsconnectwise" ),
                        "type"    => "select",
                        "choices" => $this->get_opportunity_types(),
                    ),
                    array(
                        "name"    => "marketing_campaign",
                        "label"   => esc_html__( "Marketing Campaign", "gravityformsconnectwise" ),
                        "type"    => "select",
                        "choices" => $this->get_marketing_campaign(),
                    ),
                    array(
                        "name"    => "opportunity_owner",
                        "label"   => esc_html__( "Sales Rep", "gravityformsconnectwise" ),
                        "type"    => "select",
                        "choices" => $this->get_team_members(),
                    ),
                    array(
                        "name"  => "opportunity_source",
                        "label" => esc_html__( "Source", "gravityformsconnectwise" ),
                        "type"  => "text",
                        "class" => "medium",
                    ),
                    array(
                        "name"  => "opportunity_note",
                        "label" => esc_html__( "Notes", "gravityformsconnectwise" ),
                        "type"  => "textarea",
                        "class" => "medium merge-tag-support"
                    ),
                )
            );

            $activity_fields = array(
                "title"      => esc_html__( "Activity Details", "gravityformsconnectwise" ),
                "dependency" => array(
                    "field"  => "create_activity",
                    "values" => ( "1" )
                ),
                "fields"     => array(
                    array(
                        "name"          => "activity_name",
                        "required"      => true,
                        "label"         => esc_html__( "Subject", "gravityformsconnectwise" ),
                        "type"          => "text",
                        "class"         => "medium merge-tag-support",
                        "default_value" => "Follow up with web lead"
                    ),
                    array(
                        "name"    => "activity_assigned_to",
                        "label"   => esc_html__( "Assign To", "gravityformsconnectwise" ),
                        "type"    => "select",
                        "choices" => $this->get_team_members(),
                    ),
                    array(
                        "name"    => "activity_type",
                        "label"   => esc_html__( "Type", "gravityformsconnectwise" ),
                        "type"    => "select",
                        "choices" => $this->get_activity_types(),
                    ),
                    array(
                        "name"  => "activity_note",
                        "label" => esc_html__( "Notes", "gravityformsconnectwise" ),
                        "type"  => "textarea",
                        "class" => "medium merge-tag-support"
                    )
                )
            );

            $service_ticket_fields = array(
                "title"      => esc_html__( "Service Ticket Details", "gravityformsconnectwise" ),
                "dependency" => array(
                    "field"  => "create_service_ticket",
                    "values" => ( "1" )
                ),
                "fields"     => array(
                    array(
                        "name"     => "service_ticket_summary",
                        "required" => true,
                        "label"    => esc_html__( "Summary", "gravityformsconnectwise" ),
                        "type"     => "text",
                        "class"    => "medium merge-tag-support",
                    ),
                    array(
                        "name"     => "service_ticket_board",
                        "required" => false,
                        "label"    => esc_html__( "Board", "gravityformsconnectwise" ),
                        "type"     => "select",
                        "choices"  => $this->get_service_board(),
                    ),
                    array(
                        "name"     => "service_ticket_priority",
                        "required" => false,
                        "label"    => esc_html__( "Priority", "gravityformsconnectwise" ),
                        "type"     => "select",
                        "choices"  => $this->get_service_priority(),
                    ),
                    array(
                        "name"     => "service_ticket_initial_description",
                        "required" => true,
                        "label"    => esc_html__( "Initial Description", "gravityformsconnectwise" ),
                        "type"     => "textarea",
                        "class"    => "medium merge-tag-support",
                    ),
                )
            );

            $conditional_fields = array(
                "dependency" => array( $this, "show_conditional_logic_field" ),
            );

            return array( $base_fields, $contact_fields, $company_fields, $opportunity_fields, $activity_fields, $service_ticket_fields, $conditional_fields );
        }
        public function can_create_feed() {
            return $this->is_valid_settings();
        }

        public function get_team_members(){
            $this->log_debug( __METHOD__ . "(): start getting team members from ConnectWise" );

            $team_members_list = array();

            $get_team_members_url = "system/members";
            $cw_team_members = $this->send_request( $get_team_members_url, "GET", NULL );
            $cw_team_members = json_decode( $cw_team_members["body"] );

            foreach ( $cw_team_members as $each_member ) {
                $member = array(
                        "label" => esc_html__( $each_member->name, "gravityformsconnectwise" ),
                        "value" => $each_member->identifier
                );
                array_push( $team_members_list, $member );
            }

            $this->log_debug( __METHOD__ . "(): finish getting team members from ConnectWise" );

            return $team_members_list;
        }

        public function get_departments() {
            $this->log_debug( __METHOD__ . "(): start getting departments from ConnectWise" );

            $department_list = array();

            $get_departments_url = "company/contacts/departments";
            $cw_department = $this->send_request( $get_departments_url, "GET", NULL );
            $cw_department = json_decode( $cw_department["body"] );

            foreach ( $cw_department as $each_department ) {
                $department = array(
                    "label" => esc_html__( $each_department->name, "gravityformsconnectwise" ),
                    "value" => $each_department->id
                );
                array_push( $department_list, $department );
            }

            $this->log_debug( __METHOD__ . "(): finish getting departments from ConnectWise" );

            return $department_list;
        }

        public function get_service_board() {
            $this->log_debug( __METHOD__ . "(): start getting service board from ConnectWise" );

            $board_list = array();

            $get_boards_url = "service/boards";
            $cw_board = $this->send_request( $get_boards_url, "GET", NULL );
            $cw_board = json_decode( $cw_board["body"] );

            foreach ( $cw_board as $each_board ) {
                $board = array(
                    "label" => esc_html__( $each_board->name, "gravityformsconnectwise" ),
                    "value" => $each_board->id
                );
                array_push( $board_list, $board );
            }

            $this->log_debug( __METHOD__ . "(): finish getting service board from ConnectWise" );

            return $board_list;
        }

        public function get_service_priority() {
            $this->log_debug( __METHOD__ . "(): start getting service priority from ConnectWise" );

            $priority_list = array();

            $get_prioritys_url = "service/priorities";
            $cw_priority = $this->send_request( $get_prioritys_url, "GET", NULL );
            $cw_priority = json_decode( $cw_priority["body"] );

            foreach ( $cw_priority as $each_priority ) {
                $priority = array(
                    "label" => esc_html__( $each_priority->name, "gravityformsconnectwise" ),
                    "value" => $each_priority->id
                );
                array_push( $priority_list, $priority );
            }

            $this->log_debug( __METHOD__ . "(): finish getting service priority from ConnectWise" );

            return $priority_list;
        }

        public function get_company_types() {
            $company_type_list = array();

            $get_company_type_url = "company/companies/types";
            $cw_company_type = $this->send_request( $get_company_type_url, "GET", NULL );
            $cw_company_type = json_decode( $cw_company_type["body"] );

            foreach ( $cw_company_type as $each_company_type ) {
                $company_type = array(
                    "label" => esc_html__( $each_company_type->name, "gravityformsconnectwise" ),
                    "value" => $each_company_type->id
                );
                array_push( $company_type_list, $company_type );
            }
            return $company_type_list;
        }

        public function get_contact_types() {
            $contact_type_list = array();

            $get_contact_types_url = "company/contacts/types";
            $cw_contact_types = $this->send_request( $get_contact_types_url, "GET", NULL );
            $cw_contact_types = json_decode( $cw_contact_types["body"] );

            foreach ( $cw_contact_types as $each_contact_type ) {
                $contact_type = array(
                    "label" => esc_html__( $each_contact_type->description, "gravityformsconnectwise" ),
                    "value" => $each_contact_type->id
                );
                array_push( $contact_type_list, $contact_type );
            }
            return $contact_type_list;
        }

        public function get_marketing_campaign() {
            $marketing_campaign_list = array();

            $get_campaign_url      = "/marketing/campaigns";
            $cw_marketing_campaign = $this->send_request( $get_campaign_url, "GET", NULL );
            $cw_marketing_campaign = json_decode( $cw_marketing_campaign["body"] );
            $default_campaing      = array(
                "label" => esc_html__( "---------------", "gravityformsconnectwise" ),
                "value" => NULL
            );
            array_push( $marketing_campaign_list, $default_campaing );

            foreach ( $cw_marketing_campaign as $each_marketing_campaign ) {
                $marketing_campaign = array(
                    "label" => esc_html__( $each_marketing_campaign->name, "gravityformsconnectwise" ),
                    "value" => $each_marketing_campaign->id
                );
                array_push( $marketing_campaign_list, $marketing_campaign );
            }
            return $marketing_campaign_list;
        }

        public function get_opportunity_types() {
            $opportunity_type_list = array();

            $get_opportunity_type_url = "/sales/opportunities/types";
            $cw_opportunity_type = $this->send_request( $get_opportunity_type_url, "GET", NULL );
            $cw_opportunity_type = json_decode( $cw_opportunity_type["body"] );
            $default_opportunity_type      = array(
                "label" => esc_html__( "---------------", "gravityformsconnectwise" ),
                "value" => NULL
            );
            array_push( $opportunity_type_list, $default_opportunity_type );

            foreach ( $cw_opportunity_type as $each_opportunity_type ) {
                $opportunity_type = array(
                    "label" => esc_html__( $each_opportunity_type->description, "gravityformsconnectwise" ),
                    "value" => $each_opportunity_type->id
                );
                array_push( $opportunity_type_list, $opportunity_type );
            }
            return $opportunity_type_list;
        }

        public function get_company_statuses() {
            $company_status_list = array();

            $get_company_status_url = "company/companies/statuses";
            $cw_company_status = $this->send_request( $get_company_status_url, "GET", NULL );
            $cw_company_status = json_decode( $cw_company_status["body"] );

            foreach ( $cw_company_status as $each_company_status ) {
                $company_status = array(
                    "label" => esc_html__( $each_company_status->name, "gravityformsconnectwise" ),
                    "value" => $each_company_status->id
                );
                array_push( $company_status_list, $company_status );
            }
            return $company_status_list;
        }

        public function get_activity_types() {
            $activity_type_list = array();

            $get_activity_type_url = "sales/activities/types";
            $cw_activity_type = $this->send_request( $get_activity_type_url, "GET", NULL );
            $cw_activity_type = json_decode( $cw_activity_type["body"] );

            foreach ( $cw_activity_type as $each_activity_type ) {
                $activity_type = array(
                    "label" => esc_html__( $each_activity_type->name, "gravityformsconnectwise" ),
                    "value" => $each_activity_type->id
                );
                array_push( $activity_type_list, $activity_type );
            }
            return $activity_type_list;
        }

        public function standard_fields_mapping() {
            return array(
                array(
                    "name"       => "first_name",
                    "label"      => esc_html__( "First Name", "gravityformsconnectwise" ),
                    "required"   => true,
                    "field_type" => array(
                        "name",
                        "text",
                        "hidden"
                    ),
                ),
                array(
                    "name"       => "last_name",
                    "label"      => esc_html__( "Last Name", "gravityformsconnectwise" ),
                    "required"   => true,
                    "field_type" => array(
                        "name",
                        "text",
                        "hidden"
                    ),
                ),
                array(
                    "name"       => "email",
                    "label"      => esc_html__( "Email", "gravityformsconnectwise" ),
                    "required"   => true,
                    "field_type" => array(
                        "email",
                        "text",
                        "hidden"
                    ),
                )
            );
        }

        public function custom_fields_mapping() {
            return array(
                array(
                    "label" => esc_html__( "Choose a Field", "gravityformsconnectwise" ),
                    'choices' => array(
                        array(
                            "label" => esc_html__( "Company", "gravityformsconnectwise" ),
                            "value" => "company"
                        ),
                        array(
                            "label" => esc_html__( "Address 1", "gravityformsconnectwise" ),
                            "value" => "address_1"
                        ),
                        array(
                            "label" => esc_html__( "Address 2", "gravityformsconnectwise" ),
                            "value" => "address_2"
                        ),
                        array(
                            "label" => esc_html__( "City", "gravityformsconnectwise" ),
                            "value" => "city"
                        ),
                        array(
                            "label" => esc_html__( "State", "gravityformsconnectwise" ),
                            "value" => "state"
                        ),
                        array(
                            "label" => esc_html__( "Zip", "gravityformsconnectwise" ),
                            "value" => "zip"
                        ),
                        array(
                            "label" => esc_html__( "Phone", "gravityformsconnectwise" ),
                            "value" => "phone_number"
                        ),
                        array(
                            "label" => esc_html__( "Fax", "gravityformsconnectwise" ),
                            "value" => "fax_number"
                        ),
                        array(
                            "label" => esc_html__( "Web site", "gravityformsconnectwise" ),
                            "value" => "web_site"
                        ),
                    )
                ),
            );
        }

        public function plugin_settings_fields() {
            return array(
                array(
                    "description" => $this->plugin_settings_description(),
                    "fields" => array(
                        array(
                            "name"              => "connectwise_url",
                            "label"             => "ConnectWise URL",
                            "type"              => "text",
                            "class"             => "medium",
                            "save_callback"     => array( $this, "clean_field" ),
                            "feedback_callback" => array( $this, "is_valid_settings" )
                        ),
                        array(
                            "name"              => "company_id",
                            "label"             => "Company ID",
                            "type"              => "text",
                            "class"             => "small",
                            "save_callback"     => array( $this, "clean_field" ),
                            "feedback_callback" => array( $this, "is_valid_settings" )
                        ),
                        array(
                            "name"              => "public_key",
                            "label"             => "Public API Key",
                            "type"              => "text",
                            "class"             => "small",
                            "save_callback"     => array( $this, "clean_field" ),
                            "feedback_callback" => array( $this, "is_valid_settings" )
                        ),
                        array(
                            "name"              => "private_key",
                            "label"             => "Private API Key",
                            "type"              => "text",
                            "class"             => "small",
                            "save_callback"     => array( $this, "clean_field" ),
                            "feedback_callback" => array( $this, "is_valid_settings" )
                        ),
                    )
                )
            );
        }

        public function plugin_settings_description() {
            $description  = "<p>";
            $description .= sprintf(
                'Complete the settings below to authenticate with your ConnectWise account. %1$sHere\'s how to generate API keys.%2$s',
                '<a href="https://pronto.zendesk.com/hc/en-us/articles/207946586" target="_blank">', '</a>'
            );
            $description .= "</p>";

            return $description;
        }

        public function clean_field( $field, $field_setting ) {
            return sanitize_text_field( $field_setting );
        }

        public function is_valid_settings() {
            $status = False;

            $url = "system/info";
            $connection = $this->send_request( $url, "GET", NULL );

            if ( ! is_wp_error( $connection ) and 200 == $connection["response"]["code"] ) {
                $status = True;
            } else {
                $this->log_debug( __METHOD__ . "(): response[body] => " . print_r( $connection, true ) );
            }

            return $status;
        }

        public function transform_url( $url ) {
            $wp_connectwise_url = $this->get_plugin_setting( "connectwise_url" );

            $prefix = array( "na.", "eu.", "aus." );
            $first_dot_pos = strpos( $wp_connectwise_url, "." );
            if ( true == in_array( substr( $wp_connectwise_url, 0, $first_dot_pos + 1 ), $prefix ) ) {
                $url = "https://api-" . $wp_connectwise_url . "/v4_6_release/apis/3.0/" . $url;
            } else {
                $url = "https://" . $wp_connectwise_url . "/v4_6_release/apis/3.0/" . $url;
            }
            return $url;
        }

        public function send_request( $url, $request_method, $body ) {
            if ( "system/info" != $url ) {
                $this->log_debug( "## " . __METHOD__ . "(): start sending request ##" );
            }

            $url =  $this->transform_url( $url );

            if ( false == strpos( $url, "system/info" ) ) {
                $this->log_debug( __METHOD__ . "(): url => " . print_r( $url, true ) );
                $this->log_debug( __METHOD__ . "(): request => " . print_r( $request_method, true ) );
                $this->log_debug( __METHOD__ . "(): body => " . print_r( $body, true ) );
            }

            $company_id  = $this->get_plugin_setting( "company_id" );
            $public_key  = $this->get_plugin_setting( "public_key" );
            $private_key = $this->get_plugin_setting( "private_key" );

            $args = array(
                "method"  => $request_method,
                "body"    => $body,
                "headers" => array(
                    "Accept"           => "application/vnd.connectwise.com+json; version=v2015_3",
                    "Content-type"     => "application/json" ,
                    "Authorization"    => "Basic " . base64_encode( $company_id . "+" . $public_key  . ":" . $private_key ),
                    "X-cw-overridessl" => "True"
                )
            );
            if ( $body ) {
                $args["body"] = json_encode( $body );
            }

            $response = wp_remote_request( $url, $args );

            if ( false == strpos( $url, "system/info" ) ) {
                if ( true == is_array( $response ) ) {
                    $this->log_debug( __METHOD__ . "(): response[body] => " . print_r( $response["body"], true ) );
                    $this->log_debug( __METHOD__ . "(): response[response][code] => " . print_r( $response["response"]["code"], true ) );
                } else {
                    $this->log_debug( __METHOD__ . "(): response => " . print_r( $response, true ) );
                }
                $this->log_debug( "## " . __METHOD__ . "(): finish sending request ##" );
            }

            return $response;
        }
    }

    new GFConnectWise();
}