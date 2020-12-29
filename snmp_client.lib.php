<?php
/* 
SNMP 取得主機資訊(目的主機必須安裝snmp服務並設定) 
Windows 10 開啟SNMP服務
1.控制台→應用程式與功能→選用功能→新增→簡易網路管理通訊協定(SNMP)
2.開始選單→元件服務→服務(本機)→雙擊SNMP服務→代理程式頁籤(設定聯絡人及位置，下方服務全勾)→安全性頁籤(新增public帳號，下方設定可從所有主機接受SNMP封包)
*/

$host = "127.0.0.1";
$community = "public";

$snmp = new SNMP_Client($host,$community);
$a = $snmp->getSNMPInfo();
if($a['status']==false)
{
	echo $host." connect failure";
}
else
{
	var_dump($a);
}

class SNMP_Client 
{
	private $resArr = array();
	
	/*
	array (size=8)
	  'host' => string '127.0.0.1' (length=9)		IP位置
	  'community' => string 'public' (length=6)		SNMP連線字串
	  'computer_name' => string '"MSI"' (length=5)	主機名稱
	  'status' => boolean true						連線狀態
	  'cpu' => string '23.13%' (length=6)			CPU使用率
	  'hdd' => 										硬碟使用率
	    array (size=2)
	      0 => 
	        array (size=2)
	          'name' => string '"C:\\ Label:Windows  Serial Number e09eeb96"' (length=44)
	          'usage' => string '51.86%' (length=6)
	      1 => 
	        array (size=2)
	          'name' => string '"D:\\ Label:Data  Serial Number ca50554"' (length=40)
	          'usage' => string '11.83%' (length=6)
	  'ram' => string '64.25%' (length=6)			實體記憶體使用率
	  'firefox' => boolean false					是否有啟動firefox
	*/
	
	function __construct($host="127.0.0.1", $community="public")
	{
		snmp_set_quick_print(TRUE);
		
		$this->resArr['host'] = $host;
		$this->resArr['community'] = $community;
		$this->resArr['status'] = $this->checkSNMP_Status();
	}
	
	function checkSNMP_Status()
	{
		//電腦名稱
		$cname = @snmp2_walk($this->resArr['host'], $this->resArr['community'], ".1.3.6.1.2.1.1.5.0");
		if($cname)
		{
			$this->resArr['computer_name'] = $cname[0];
			return true;
		}
		return false;
	}
	
	function getSNMPInfo($type="all")
	{
		if($this->resArr['status']===true)
		{
			if($type=="all" || $type=="cpu")
			{
				//cpu
				$cpus = snmp2_walk($this->resArr['host'], $this->resArr['community'], ".1.3.6.1.2.1.25.3.3.1.2");
				$ct = 0;
				foreach ($cpus as $val)
				{
					$ct += $val;
				}
				$cpu_usage = round(($ct/count($cpus)),2);
				$this->resArr['cpu'] = $cpu_usage."%";
			}
			
			if($type=="all" || $type=="ram" || $type=="hdd")
			{
				//實體記憶體大小
				$hrHardwayRAM = snmp2_walk($this->resArr['host'], $this->resArr['community'], ".1.3.6.1.2.1.25.2.2.0");
				//記憶體類型
				$hrStorageType = snmp2_walk($this->resArr['host'], $this->resArr['community'], ".1.3.6.1.2.1.25.2.3.1.3");
				//每個箸/塊的大小
				$hrStorageAreaUnits = snmp2_walk($this->resArr['host'], $this->resArr['community'], ".1.3.6.1.2.1.25.2.3.1.4");
				//一個磁盤分為多少塊/箸, 總大小
				$hrStorageAllocationUnits = snmp2_walk($this->resArr['host'], $this->resArr['community'], ".1.3.6.1.2.1.25.2.3.1.5");
				//已經使用的塊/箸
				$hrStorageUsed = snmp2_walk($this->resArr['host'], $this->resArr['community'], ".1.3.6.1.2.1.25.2.3.1.6");
				foreach($hrStorageType as $key=>$val)
				{
					$i = 0;
					/*
					C: 盤大小為4096×20972849=85904789504bytes or 80GB
					C: 盤以用空間4096×7389539=7566887936bytes or 7.04GB
					C：盤使用率為7.04/80*100% = 8%
					使用類似的方法可以計算出其它盤符的使用率和空間數據
					物理內存占用率：(65536*45513/1024)/4088864*100%=71.24%
					*/
					
					//實體記憶體
					if(in_array(strtolower($val),array('"physical memory"')) && ($type=="all" || $type=="ram"))
					{
						$memory_usage = round(($hrStorageAreaUnits[$key]*$hrStorageUsed[$key]/1024)/$hrHardwayRAM[0]*100,2);
						$this->resArr['ram'] = $memory_usage."%";
					}
					elseif($val!='"Virtual Memory"' && ($type=="all" || $type=="hdd"))
					{
						$hd_usage = round((($hrStorageAreaUnits[$key]*$hrStorageUsed[$key])/($hrStorageAreaUnits[$key]*$hrStorageAllocationUnits[$key])*100),2);
						$this->resArr['hdd'][] = array("name"=>$val,"usage"=>$hd_usage."%");
					}
				}
			}
			
			if($type=="all" || $type=="ff")
			{
				//檢查是否有啟動firefox
				$this->resArr['firefox'] = false;
				$a = snmp2_walk($this->resArr['host'], $this->resArr['community'], ".1.3.6.1.2.1.25.4.2.1.2");
				
				foreach($a as $val)
				{
					if(strpos($val,"firefox"))
					{
						$this->resArr['firefox'] = true;
						break;
					}
				}
			}
		}
		return $this->resArr;
	}
}
?>