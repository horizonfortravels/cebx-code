<?php

return [
    'common' => [
        'address_book' => 'Address book',
        'create_cta' => 'Add saved address',
        'save_cta' => 'Save address',
        'update_cta' => 'Update address',
        'back_to_index' => 'Back to address book',
        'manage_link' => 'Manage address book',
        'use_saved_cta' => 'Use saved addresses',
        'clear_saved_cta' => 'Clear selection',
        'new_address_cta' => 'New address',
        'sender_picker' => 'Saved sender address',
        'recipient_picker' => 'Saved recipient address',
        'picker_placeholder' => 'Choose an address',
        'picker_help' => 'Select a saved address to prefill the sender or recipient section. You can still edit the fields before saving the draft.',
        'selected_sender' => 'Saved sender address applied',
        'selected_recipient' => 'Saved recipient address applied',
        'type' => 'Type',
        'label' => 'Label',
        'contact_name' => 'Contact name',
        'company_name' => 'Company',
        'phone' => 'Phone',
        'email' => 'Email',
        'address_line_1' => 'Address line 1',
        'address_line_2' => 'Address line 2',
        'city' => 'City',
        'state' => 'State / province',
        'postal_code' => 'Postal code',
        'country' => 'Country (ISO-2)',
        'location' => 'Location',
        'updated_at' => 'Updated at',
        'actions' => 'Actions',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'use_for_shipment' => 'Use in shipment create',
        'use_as_sender' => 'Use as sender',
        'use_as_recipient' => 'Use as recipient',
        'not_available' => 'Not available',
    ],
    'types' => [
        'sender' => 'Sender only',
        'recipient' => 'Recipient only',
        'both' => 'Sender and recipient',
    ],
    'stats' => [
        'total' => 'Saved addresses',
        'sender_ready' => 'Sender-ready',
        'recipient_ready' => 'Recipient-ready',
    ],
    'index' => [
        'b2c' => [
            'title' => 'Personal address book',
            'description' => 'Save the sender and recipient addresses you reuse most so your next shipment draft starts faster.',
            'empty_state' => 'No saved addresses yet. Add one to reuse it in the shipment form.',
            'guidance_title' => 'How this helps',
            'guidance_cards' => [
                'reuse' => [
                    'title' => 'Reuse frequent locations',
                    'body' => 'Store the sender and recipient addresses you return to often, then pull them into a new shipment draft in one step.',
                ],
                'edit' => [
                    'title' => 'Keep details current',
                    'body' => 'Update phone, city, or address details here whenever a contact changes.',
                ],
            ],
        ],
        'b2b' => [
            'title' => 'Team address book',
            'description' => 'Keep shared warehouse, customer, and return addresses in one tenant-scoped list for the organization team.',
            'empty_state' => 'No saved addresses yet. Add one for the team to reuse in shipment creation.',
            'guidance_title' => 'Why teams use this',
            'guidance_cards' => [
                'reuse' => [
                    'title' => 'Cut repetitive entry',
                    'body' => 'Shared origin and destination addresses stay available to the same tenant team members during shipment creation.',
                ],
                'safety' => [
                    'title' => 'Keep account boundaries clear',
                    'body' => 'Only addresses from the current organization account are shown and reused on this portal.',
                ],
            ],
        ],
    ],
    'form' => [
        'b2c' => [
            'create_title' => 'Add a saved address',
            'create_description' => 'Store a sender or recipient address for the current individual account.',
            'edit_title' => 'Edit saved address',
            'edit_description' => 'Update this saved address before reusing it in another shipment.',
        ],
        'b2b' => [
            'create_title' => 'Add a team address',
            'create_description' => 'Store a sender or recipient address for the current organization account.',
            'edit_title' => 'Edit team address',
            'edit_description' => 'Update this shared address before the team reuses it in another shipment.',
        ],
    ],
    'flash' => [
        'created' => 'Saved address added successfully.',
        'updated' => 'Saved address updated successfully.',
        'deleted' => 'Saved address removed successfully.',
    ],
    'validation' => [
        'state_required' => 'State or province is required when the country is the United States.',
    ],
];
