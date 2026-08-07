<?php

namespace App\Support;

final class AssetCheckoutPolicy
{
    public const ICT_POLICY_URL = 'https://desb-my.sharepoint.com/:b:/g/personal/desb_kl_desb_onmicrosoft_com/Ee12i-lyyZhOgs1Z7lZvQRwBL56hyiIDqetFVJtIzl-Kyg?e=aTkv43';

    /** @return array<string, string> */
    public static function items(): array
    {
        return [
            'custody' => 'I am responsible for the computer equipment while it is in my custody and will take precautions to prevent others from gaining access to it. If it is lost or stolen, I am liable for the replacement cost, will inform management immediately, and will lodge a police report.',
            'travel_storage' => 'When travelling in a car, I will secure the equipment in the vehicle\'s trunk. During overnight stays, I will remove it from the car and secure it in the hotel room.',
            'physical_contact' => 'I will maintain physical contact with the equipment in situations that increase the risk of theft, such as airports, hotels, and public locations.',
            'acceptable_use' => 'Except for work purposes, I will not use the company computer to play games, access offensive materials, or download entertainment materials such as movies or MP3 files.',
            'software' => 'I will not install software on the computer without the express permission of the IT Department.',
            'personal_files' => 'I will not store music, video files, or other personal files on the computer. I understand that the IT Department may delete such files without prior notice.',
            'network_settings' => 'I will not change the IP address, gateway, subnet mask, or DNS settings assigned by the IT Department.',
            'privileges' => 'I understand that breaking these rules and regulations could result in the termination of my user privileges.',
            'ict_policy' => 'I have read and understood the Dayang Enterprise Sdn. Bhd. ICT Policy linked below.',
        ];
    }
}
