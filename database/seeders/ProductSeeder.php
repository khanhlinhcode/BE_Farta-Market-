<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $desc = '<ul>
        <li>
          <p>
            Rau củ chứa rất nhiều vitamin và chất dinh dưỡng nên mang đến
            rất nhiều lợi ích cho sức khỏe con người.&nbsp;
          </p>
        </li>
        <li>
          <p>Hỗ trợ giảm cân</p>
        </li>
        <li>
          <p>
            Giảm nguy cơ mắc các bệnh về tim mạch, béo phì và cả ung thư
          </p>
        </li>
        <li>
          <p>Tăng cường sức đề kháng của cơ thể</p>
        </li>
        <li>
          <p>Cải thiện thị lực</p>
        </li>
        <li>
          <p>Điều hòa đường huyết</p>
        </li>
        <li>
          <p>Giảm cholesterol trong máu</p>
        </li>
        <li>
          <p>Điều hòa huyết áp</p>
        </li>
      </ul>
      <p>
        <br />
        <strong>Cách chọn rau củ tươi ngon</strong>
      </p>
      <ul>
        <li>
          <p>
            Dựa vào hình dáng bên ngoài: Nên ưu tiên chọn các loại rau củ có
            phần vỏ không có các vết sâu, cuống lá không bị nhũn, thâm đen.
            Tránh chọn những loại quả có vẻ ngoài to tròn, căng lớn, bởi đây
            có thể là những quả đã bị tiêm thuốc, không an toàn cho sức
            khỏe.
          </p>
        </li>
        <li>
          <p>
            Dựa vào màu sắc: Màu sắc của các loại rau củ thường tươi mới,
            không có các vết xước, héo hay quá đậm màu. Các loại củ có màu
            quá xanh hoặc quá bóng sẽ không hẳn là an toàn cho sức khỏe
            người dùng.
          </p>
        </li>
        <li>
          <p>
            Dựa vào mùi hương: Mùi hương tự nhiên của các loại rau củ sẽ là
            mùi đặc trưng theo từng loại chứ không phải là mùi hắc, nồng khó
            chịu. Nếu các loại củ bạn đang chọn có mùi hóa chất hãy ngưng sử
            dụng ngay.
          </p>
        </li>
        <li>
          <p>
            Dựa vào cảm nhận khi cầm: Những loại củ quả cầm chắc tay, kích
            thước vừa phải, không quá to sẽ thường ngon hơn những loại to
            lớn nhưng khối lượng nhẹ. Một số loại rau củ bạn chỉ nên chọn
            những quả nhỏ, đều tay sẽ ngon hơn.
          </p>
        </li>
      </ul>';


        $img="/assets/users/images/featured/feature-";
        $marketSortDescription = 'Farta Market cung cấp thực phẩm tươi sạch, chọn lọc kỹ mỗi ngày và phù hợp cho bữa ăn gia đình.';
        $categoryIds = Category::pluck('id', 'name');
        $products = [
            [
                'name'=> 'Thịt bò nạt',
                'img'=> $img.'1.png',
                'price'=> 200000,
                'inventory'=> 20,
                'description'=> 'Thịt bò nạt Úc đông lạnh, ít mỡ, phù hợp nấu lẩu hoặc áp chảo.',
                'sort_description'=> 'Thịt bò nạt Úc ít mỡ, tiện chế biến các món lẩu, xào hoặc áp chảo.',
                'facebook' => 'https://facebook.com/example',
                'linkedin' => 'https://linkedin.com/example',
                'twitter' => 'https://twitter.com/example',
                'instagram' => 'https://instagram.com/example',
                'category_id' => $categoryIds['Thịt Tươi']
            ],
            [
                'name'=> 'Chuối',
                'img'=> $img.'2.png',
                'price'=> 17800,
                'inventory'=> 20,
                'description'=> $desc,
                'sort_description'=> $marketSortDescription,
                'facebook' => 'https://facebook.com/example',
                'linkedin' => 'https://linkedin.com/example',
                'twitter' => 'https://twitter.com/example',
                'instagram' => 'https://instagram.com/example',
                'category_id' => $categoryIds['Trái Cây']
            ],
            [
                'name'=> 'Ổi',
                'img'=> $img.'3.png',
                'price'=> 25000,
                'inventory'=> 20,
                'description'=> $desc,
                'sort_description'=> $marketSortDescription,
                'facebook' => 'https://facebook.com/example',
                'linkedin' => 'https://linkedin.com/example',
                'twitter' => 'https://twitter.com/example',
                'instagram' => 'https://instagram.com/example',
                'category_id' => $categoryIds['Trái Cây']
            ],
            [
                'name'=> 'Dưa hấu',
                'img'=> $img.'4.png',
                'price'=> 44020,
                'inventory'=> 20,
                'description'=> $desc,
                'sort_description'=> $marketSortDescription,
                'facebook' => 'https://facebook.com/example',
                'linkedin' => 'https://linkedin.com/example',
                'twitter' => 'https://twitter.com/example',
                'instagram' => 'https://instagram.com/example',
                'category_id' => $categoryIds['Trái Cây']
            ],
            [
                'name'=> 'Nho tím',
                'img'=> $img.'5.png',
                'price'=> 120000,
                'inventory'=> 20,
                'description'=> $desc,
                'sort_description'=> $marketSortDescription,
                'facebook' => 'https://facebook.com/example',
                'linkedin' => 'https://linkedin.com/example',
                'twitter' => 'https://twitter.com/example',
                'instagram' => 'https://instagram.com/example',
                'category_id' => $categoryIds['Trái Cây']
            ],
            [
                'name'=> 'Hamburger',
                'img'=> $img.'6.png',
                'price'=> 86000,
                'inventory'=> 20,
                'description'=> 'Burger bò tươi, kẹp rau và sốt đặc biệt, ăn liền tiện lợi.',
                'sort_description'=> 'Burger bò tươi kèm rau giòn và sốt đặc biệt, phù hợp bữa ăn nhanh tiện lợi.',
                'facebook' => 'https://facebook.com/example',
                'linkedin' => 'https://linkedin.com/example',
                'twitter' => 'https://twitter.com/example',
                'instagram' => 'https://instagram.com/example',
                'category_id' => $categoryIds['Thức Ăn Nhanh']
            ],
            [
                'name'=> 'Xoài keo',
                'img'=> $img.'7.png',
                'price'=> 69000,
                'inventory'=> 20,
                'description'=> $desc,
                'sort_description'=> $marketSortDescription,
                'facebook' => 'https://facebook.com/example',
                'linkedin' => 'https://linkedin.com/example',
                'twitter' => 'https://twitter.com/example',
                'instagram' => 'https://instagram.com/example',
                'category_id' => $categoryIds['Trái Cây']
            ],
            [
                'name'=> 'Táo Úc',
                'img'=> $img.'8.png',
                'price'=> 53000,
                'inventory'=> 20,
                'description'=> $desc,
                'sort_description'=> $marketSortDescription,
                'facebook' => 'https://facebook.com/example',
                'linkedin' => 'https://linkedin.com/example',
                'twitter' => 'https://twitter.com/example',
                'instagram' => 'https://instagram.com/example',
                'category_id' => $categoryIds['Trái Cây']
            ],
            [
                'name'=> 'Cam Tươi',
                'img'=> $img.'1.png',
                'price'=> 45000,
                'inventory'=> 30,
                'description'=> 'Cam tươi nhập khẩu, vỏ mỏng, nhiều nước, ngọt tự nhiên.',
                'sort_description'=> 'Cam tươi mọng nước, vị ngọt thanh, phù hợp dùng trực tiếp hoặc ép nước mỗi ngày.',
                'facebook' => 'https://facebook.com/example',
                'linkedin' => 'https://linkedin.com/example',
                'twitter' => 'https://twitter.com/example',
                'instagram' => 'https://instagram.com/example',
                'category_id' => $categoryIds['Trái Cây']
            ],
            [
                'name'=> 'Rau Củ Tươi',
                'img'=> $img.'3.png',
                'price'=> 65000,
                'inventory'=> 25,
                'description'=> $desc,
                'sort_description'=> 'Combo rau củ tươi được chọn lọc trong ngày, tiện lợi cho bữa ăn gia đình.',
                'facebook' => 'https://facebook.com/example',
                'linkedin' => 'https://linkedin.com/example',
                'twitter' => 'https://twitter.com/example',
                'instagram' => 'https://instagram.com/example',
                'category_id' => $categoryIds['Rau Củ']
            ],
            [
                'name'=> 'Sữa Hộp',
                'img'=> $img.'4.png',
                'price'=> 32000,
                'inventory'=> 40,
                'description'=> 'Sữa hộp nguyên chất, giàu canxi và vitamin D, phù hợp cho cả gia đình.',
                'sort_description'=> 'Sữa hộp tiện dùng, bảo quản dễ, phù hợp bổ sung dinh dưỡng hằng ngày.',
                'facebook' => 'https://facebook.com/example',
                'linkedin' => 'https://linkedin.com/example',
                'twitter' => 'https://twitter.com/example',
                'instagram' => 'https://instagram.com/example',
                'category_id' => $categoryIds['Sữa']
            ],
            [
                'name'=> '[QA] Sản phẩm hết hàng mẫu',
                'img'=> $img.'2.png',
                'price'=> 39000,
                'inventory'=> 0,
                'is_active' => true,
                'description'=> 'Sản phẩm mẫu dùng để QA trạng thái hết hàng và chặn thêm vào giỏ.',
                'sort_description'=> 'Sản phẩm QA hết hàng, dùng để kiểm thử giao diện và nghiệp vụ tồn kho.',
                'facebook' => 'https://facebook.com/example',
                'linkedin' => 'https://linkedin.com/example',
                'twitter' => 'https://twitter.com/example',
                'instagram' => 'https://instagram.com/example',
                'category_id' => $categoryIds['Trái Cây']
            ],
            [
                'name'=> '[QA] Sữa hết hàng mẫu',
                'img'=> $img.'4.png',
                'price'=> 32000,
                'inventory'=> 0,
                'is_active' => true,
                'description'=> 'Sản phẩm mẫu dùng để QA trạng thái sữa hết hàng.',
                'sort_description'=> 'Sản phẩm QA hết hàng trong danh mục Sữa.',
                'facebook' => 'https://facebook.com/example',
                'linkedin' => 'https://linkedin.com/example',
                'twitter' => 'https://twitter.com/example',
                'instagram' => 'https://instagram.com/example',
                'category_id' => $categoryIds['Sữa']
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['name' => $product['name']],
                $product
            );
        }
    }
}
