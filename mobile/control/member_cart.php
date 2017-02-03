<?php
/**
 * 我的购物车
 *
 *
 *
 *
 */

defined('NlWxShop') or exit('Access Invalid!');

class member_cartControl extends mobileMemberControl {

	public function __construct() {
		parent::__construct();
	}

    /**
     * 购物车列表
     */
    public function cart_listOp() {
        $model_cart = Model('cart');
        $model_goods = Model('goods');
        /* wqw@newland 添加开始   　**/
        /* 时间：2015/06/08       **/
        /* 功能ID：ADMIN006       **/
        $model_store = Model('store');
        $temp = $model_store->goods_vip_list();
        if (!empty($temp)){
                foreach ($temp as $value){
                        $stroe_vip_list[] = $value['store_id'];
                }
        }else{
            $stroe_vip_list[] = '';
        }
        /* wqw@newland 添加结束   **/
        $condition = array('buyer_id' => $this->member_info['member_id']);
        $cart_list = $model_cart->listCart('db', $condition);
        $sum = 0;
        foreach ($cart_list as $key => $value) {
            $cart_list[$key]['goods_image_url'] = cthumb($value['goods_image'], $value['store_id']);
            $cart_list[$key]['goods_sum'] = ncPriceFormat($value['goods_price'] * $value['goods_num']);
            /* lyq@newland 添加开始 **/
            /* 时间：2015/11/03     **/
            // 根据商品id获取限购数量
            $cart_list[$key]['purchase_limit'] = $model_goods->getPurchaseLimitListByGoodsID($value['goods_id']);
            /* lyq@newland 添结束 **/
            $sum += $cart_list[$key]['goods_sum'];
        }
        /* wqw@newland 修改开始   　**/
        /* 时间：2015/06/08       **/
        /* 功能ID：ADMIN006       **/
        output_data(array('cart_list' => $cart_list,'stroe_vip_list'=>$stroe_vip_list, 'sum' => ncPriceFormat($sum)));
        /* wqw@newland 修改结束   **/
    }

    /**
     * 购物车添加
     */
    public function cart_addOp() {
        $goods_id = intval($_POST['goods_id']);
        $quantity = intval($_POST['quantity']);
        if($goods_id <= 0 || $quantity <= 0) {
            output_error('参数错误');
        }

        $model_goods = Model('goods');
        $model_cart	= Model('cart');
        $logic_buy_1 = Logic('buy_1');

        $goods_info = $model_goods->getGoodsOnlineInfoAndPromotionById($goods_id);

        //验证是否可以购买
		if(empty($goods_info)) {
            output_error('商品已下架或不存在');
		}

		//抢购
		$logic_buy_1->getGroupbuyInfo($goods_info);
		
		//限时折扣
		$logic_buy_1->getXianshiInfo($goods_info,$quantity);

        if ($goods_info['store_id'] == $this->member_info['store_id']) {
            output_error('不能购买自己发布的商品');
		}
		if(intval($goods_info['goods_storage']) < 1 || intval($goods_info['goods_storage']) < $quantity) {
            output_error('库存不足');
		}

        $param = array();
        $param['buyer_id']	= $this->member_info['member_id'];
        $param['store_id']	= $goods_info['store_id'];
        $param['goods_id']	= $goods_info['goods_id'];
        $param['goods_name'] = $goods_info['goods_name'];
        $param['goods_price'] = $goods_info['goods_price'];
        $param['goods_image'] = $goods_info['goods_image'];
        $param['store_name'] = $goods_info['store_name'];

        $result = $model_cart->addCart($param, 'db', $quantity);
        if($result) {
            output_data('1');
        } else {
            /* lyq@newland 修改开始    **/
            /* 时间：2015/06/17        **/
            // 错误信息
            output_error('添加失败');
            /* lyq@newland 修改结束    **/
        }
    }

    /**
     * 购物车删除
     */
    public function cart_delOp() {
        $cart_id = intval($_POST['cart_id']);

        $model_cart = Model('cart');

        if($cart_id > 0) {
            $condition = array();
            $condition['buyer_id'] = $this->member_info['member_id'];
            $condition['cart_id'] = $cart_id;

            $model_cart->delCart('db', $condition);
        }

        output_data('1');
    }

    /**
     * 更新购物车购买数量
     */
    public function cart_edit_quantityOp() {
		$cart_id = intval(abs($_POST['cart_id']));
		$quantity = intval(abs($_POST['quantity']));
		if(empty($cart_id) || empty($quantity)) {
            output_error('参数错误');
		}

		$model_cart = Model('cart');

        $cart_info = $model_cart->getCartInfo(array('cart_id'=>$cart_id, 'buyer_id' => $this->member_info['member_id']));

        //检查是否为本人购物车
        if($cart_info['buyer_id'] != $this->member_info['member_id']) {
            output_error('参数错误');
        }

        //检查库存是否充足
        if(!$this->_check_goods_storage($cart_info, $quantity, $this->member_info['member_id'])) {
            output_error('库存不足');
        }

		$data = array();
        $data['goods_num'] = $quantity;
        $update = $model_cart->editCart($data, array('cart_id'=>$cart_id));
		if ($update) {
		    $return = array();
            $return['quantity'] = $quantity;
			$return['goods_price'] = ncPriceFormat($cart_info['goods_price']);
			$return['total_price'] = ncPriceFormat($cart_info['goods_price'] * $quantity);
            output_data($return);
		} else {
            output_error('修改失败');
		}
    }

    /**
     * 检查库存是否充足
     */
    private function _check_goods_storage($cart_info, $quantity, $member_id) {
        $model_goods= Model('goods');
        $model_bl = Model('p_bundling');
        $logic_buy_1 = Logic('buy_1');

        if ($cart_info['bl_id'] == '0') {
            //普通商品
            $goods_info	= $model_goods->getGoodsOnlineInfoAndPromotionById($cart_info['goods_id']);

            //抢购
            $logic_buy_1->getGroupbuyInfo($goods_info);

            //限时折扣
            $logic_buy_1->getXianshiInfo($goods_info,$quantity);

            /* lyq@newland 修改开始    **/
            /* 时间：2015/06/01        **/
            /* 功能ID：SHOP015         **/
            // 修改：判断$goods_info['goods_num']是否为空
            $quantity = $goods_info['goods_num'] == NULL ? $quantity : $goods_info['goods_num'];
            /* lyq@newland 修改结束    **/
            
            if(intval($goods_info['goods_storage']) < $quantity) {
                return false;
            }
        } else {
            //优惠套装商品
            $bl_goods_list = $model_bl->getBundlingGoodsList(array('bl_id' => $cart_info['bl_id']));
            $goods_id_array = array();
            foreach ($bl_goods_list as $goods) {
                $goods_id_array[] = $goods['goods_id'];
            }
            $bl_goods_list = $model_goods->getGoodsOnlineListAndPromotionByIdArray($goods_id_array);

            //如果有商品库存不足，更新购买数量到目前最大库存
            foreach ($bl_goods_list as $goods_info) {
                if (intval($goods_info['goods_storage']) < $quantity) {
                    return false;
                }
            }
        }
        return true;
    }

}
