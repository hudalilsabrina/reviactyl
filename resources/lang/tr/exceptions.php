<?php

return [
    'daemon_connection_failed' => 'Daemon ile iletişim kurulurken bir istisna oluştu ve HTTP/:code yanıt kodu alındı. Bu istisna günlüğe kaydedildi.',
    'node' => [
        'servers_attached' => 'Bir düğümün silinebilmesi için ona bağlı hiçbir sunucu bulunmamalıdır.',
        'daemon_off_config_updated' => 'Daemon yapılandırması güncellendi, ancak Daemon üzerindeki yapılandırma dosyasını otomatik olarak güncellemeye çalışılırken bir hata oluştu. Bu değişikliklerin uygulanması için daemon yapılandırma dosyasını (config.yml) manuel olarak güncellemeniz gerekecektir.',
    ],
    'allocations' => [
        'server_using' => 'Bu tahsise şu anda bir sunucu atanmış durumda. Bir tahsis, yalnızca atalı bir sunucu yoksa silinebilir.',
        'too_many_ports' => 'Tek bir aralıkta 1000\'den fazla port eklemek desteklenmemektedir.',
        'invalid_mapping' => ':port için sağlanan eşleştirme geçersiz ve işlenemedi.',
        'cidr_out_of_range' => 'CIDR gösterimi yalnızca /25 ile /32 arasındaki maskelere izin verir.',
        'port_out_of_range' => 'Tahsis edilen portlar 1024\'ten büyük ve 65535\'ten küçük veya eşit olmalıdır.',
    ],
    'nest' => [
        'delete_has_servers' => 'Ona bağlı aktif sunucuları olan bir Nest, Panelden silinemez.',
        'egg' => [
            'delete_has_servers' => 'Ona bağlı aktif sunucuları olan bir Egg, Panelden silinemez.',
            'invalid_copy_id' => 'Bir komut dosyasını kopyalamak için seçilen Egg ya mevcut değil ya da scriptin kendisi kopyalanıyor.',
            'must_be_child' => 'Bu Egg için "Ayarları Şuradan Kopyala" yönergesi, seçilen Nest için bir alt seçenek olmalıdır.',
            'has_children' => 'Bu Egg, bir veya daha fazla başka Egg\'in ebeveynidir. Lütfen bu Egg\'i silmeden önce o Egg\'leri silin.',
        ],
        'variables' => [
            'env_not_unique' => ':name ortam değişkeni bu Egg için benzersiz olmalıdır.',
            'reserved_name' => ':name ortam değişkeni korumalıdır ve bir değişkene atanamaz.',
            'bad_validation_rule' => '":rule" doğrulama kuralı, bu uygulama için geçerli bir kural değildir.',
        ],
        'importer' => [
            'json_error' => 'JSON dosyasını ayrıştırmaya çalışırken bir hata oluştu: :error.',
            'file_error' => 'Sağlanan JSON dosyası geçerli değil.',
            'invalid_json_provided' => 'Sağlanan JSON dosyası tanınabilir bir formatta değil.',
        ],
    ],
    'subusers' => [
        'editing_self' => 'Kendi alt kullanıcı hesabınızı düzenlemeye izin verilmez.',
        'user_is_owner' => 'Sunucu sahibini bu sunucu için alt kullanıcı olarak ekleyemezsiniz.',
        'subuser_exists' => 'Bu e-posta adresine sahip bir kullanıcı zaten bu sunucu için alt kullanıcı olarak atanmış.',
    ],
    'databases' => [
        'delete_has_databases' => 'Ona bağlı aktif veritabanları olan bir veritabanı sunucusu silinemez.',
    ],
    'tasks' => [
        'chain_interval_too_long' => 'Zincirleme bir görev için maksimum aralık süresi 15 dakikadır.',
    ],
    'locations' => [
        'has_nodes' => 'Ona bağlı aktif düğümleri olan bir konum silinemez.',
    ],
    'users' => [
        'node_revocation_failed' => '<a href=":link">Düğüm #:node</a> üzerindeki anahtarlar iptal edilemedi. :error',
    ],
    'deployment' => [
        'no_viable_nodes' => 'Otomatik dağıtım için belirtilen gereksinimleri karşılayan hiçbir düğüm bulunamadı.',
        'no_viable_allocations' => 'Otomatik dağıtım gereksinimlerini karşılayan hiçbir tahsis bulunamadı.',
    ],
    'api' => [
        'resource_not_found' => 'İstenen kaynak bu sunucuda mevcut değil.',
    ],
    'social' => [
        'unlink_only_login' => 'Önce bir parola belirlemeden tek giriş yönteminizin bağlantısını kesemezsiniz.',
    ],
];
